<?php

return [
    'install_welcome' => 'Installation et configuration interactive du package PMB ACL',
    'prompt_mode' => 'Quel modèle d\'autorisation souhaitez-vous appliquer ?',
    'mode_hybrid' => 'Hybride (Rôles + Permissions directes + Wildcards)',
    'mode_rbac' => 'RBAC (Rôles et Permissions de rôles uniquement)',
    'mode_pbac' => 'PBAC (Permissions directes attribuées aux utilisateurs)',
    'prompt_wildcards' => 'Souhaitez-vous activer l\'évaluation des Wildcards (ex: orders.*) ?',
    'prompt_pk' => 'Quel type de clé primaire préférez-vous pour les tables ACL ?',
    'prompt_user_fk' => 'Quel type de clé utilise la table users pour user_id ?',
    'prompt_run_migrations' => 'Souhaitez-vous exécuter les migrations maintenant ?',
    'publishes_config' => 'Publication du fichier de configuration...',
    'publishes_migrations' => 'Publication des migrations dynamiques...',
    'install_success' => 'Package PMB ACL installé et configuré avec succès.',
    'unauthorized_role' => 'Accès refusé. Un des rôles suivants est requis : :roles',
    'unauthorized_permission' => 'Accès refusé. La permission suivante est requise : :permission',
];