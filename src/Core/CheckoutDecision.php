<?php
namespace WcQualiopiSteps\Core;

defined( 'ABSPATH' ) || exit;

/**
 * Classe responsable de la logique de décision pour le checkout
 * 
 * Cette classe encapsule toute la logique métier pour déterminer
 * si un checkout doit être autorisé ou bloqué selon l'état des tests Qualiopi.
 * 
 * @package WcQualiopiSteps\Core
 */
class CheckoutDecision {

    /**
     * Structure de réponse pour une décision de checkout
     */
    public readonly bool $allow;
    public readonly string $reason;
    public readonly ?string $redirect_url;
    public readonly array $details;

    /**
     * Constructeur privé - utiliser decide() pour créer des instances
     */
    private function __construct(
        bool $allow,
        string $reason,
        ?string $redirect_url = null,
        array $details = []
    ) {
        $this->allow = $allow;
        $this->reason = $reason;
        $this->redirect_url = $redirect_url;
        $this->details = $details;
    }

    /**
     * Point d'entrée principal pour prendre une décision de checkout
     * 
     * @param array $context Contexte de la décision avec clés:
     *   - flags: array des flags du plugin (enforce_checkout, etc.)
     *   - cart: array avec product_id
     *   - user: array avec id utilisateur
     *   - query: array avec tp_token si présent
     *   - session: array avec données de session (solved)
     *   - usermeta: array avec données utilisateur (ok)
     *   - mapping: array avec configuration du mapping
     * 
     * @return CheckoutDecision Instance de décision
     */
    public static function decide(array $context): self
    {
        // 1. Vérifier si l'enforcement est activé
        if (!($context['flags']['enforce_checkout'] ?? false)) {
            return new self(
                allow: true,
                reason: 'flag_off',
                details: ['message' => 'Enforcement désactivé']
            );
        }

        // 2. Vérifier si le panier est vide ou sans produit
        if (empty($context['cart']['product_id'])) {
            return new self(
                allow: true,
                reason: 'no_product',
                details: ['message' => 'Aucun produit dans le panier']
            );
        }

        $product_id = $context['cart']['product_id'];
        $user_id = $context['user']['id'] ?? 0;

        // 3. Vérifier si le mapping existe et est actif
        if (!($context['mapping']['active'] ?? false)) {
            return new self(
                allow: true,
                reason: 'no_mapping',
                details: [
                    'message' => 'Aucun mapping actif pour ce produit',
                    'product_id' => $product_id
                ]
            );
        }

        // 4. Vérifier token de validation temporaire (priorité haute)
        if (!empty($context['query']['tp_token'])) {
            // TODO: Implémenter la validation du token
            // Pour l'instant, on considère que sa présence = validation OK
            return new self(
                allow: true,
                reason: 'temp_token',
                details: [
                    'message' => 'Token temporaire valide',
                    'token' => $context['query']['tp_token']
                ]
            );
        }

        // 5. Vérifier validation en session
        $session_solved = $context['session']['solved'][$product_id] ?? false;
        if ($session_solved) {
            return new self(
                allow: true,
                reason: 'session_ok',
                details: [
                    'message' => 'Test validé en session',
                    'product_id' => $product_id
                ]
            );
        }

        // 6. Vérifier validation en usermeta (persistante)
        $usermeta_ok = $context['usermeta']['ok'][$product_id] ?? false;
        if ($usermeta_ok && $user_id > 0) {
            return new self(
                allow: true,
                reason: 'usermeta_ok',
                details: [
                    'message' => 'Test validé en usermeta',
                    'product_id' => $product_id,
                    'user_id' => $user_id
                ]
            );
        }

        // 7. Aucune validation trouvée - bloquer et rediriger
        $test_url = $context['mapping']['test_page_url'] ?? null;
        
        return new self(
            allow: false,
            reason: 'no_validation',
            redirect_url: $test_url,
            details: [
                'message' => 'Test de positionnement requis',
                'product_id' => $product_id,
                'user_id' => $user_id,
                'test_url' => $test_url
            ]
        );
    }

    /**
     * Créer une décision d'autorisation
     */
    public static function allow(string $reason, array $details = []): self
    {
        return new self(
            allow: true,
            reason: $reason,
            details: $details
        );
    }

    /**
     * Créer une décision de blocage
     */
    public static function block(string $reason, ?string $redirect_url = null, array $details = []): self
    {
        return new self(
            allow: false,
            reason: $reason,
            redirect_url: $redirect_url,
            details: $details
        );
    }

    /**
     * Vérifier si la décision autorise le checkout
     */
    public function isAllowed(): bool
    {
        return $this->allow;
    }

    /**
     * Vérifier si la décision bloque le checkout
     */
    public function isBlocked(): bool
    {
        return !$this->allow;
    }

    /**
     * Obtenir un message descriptif de la décision
     */
    public function getMessage(): string
    {
        return $this->details['message'] ?? 'Décision: ' . $this->reason;
    }

    /**
     * Convertir en tableau pour logging ou debugging
     */
    public function toArray(): array
    {
        return [
            'allow' => $this->allow,
            'reason' => $this->reason,
            'redirect_url' => $this->redirect_url,
            'details' => $this->details
        ];
    }
}
