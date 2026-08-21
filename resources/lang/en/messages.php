<?php

return [
    'install_welcome' => 'Interactive Setup and Configuration for PMB ACL Package',
    'prompt_mode' => 'Which authorization model do you want to enforce?',
    'mode_hybrid' => 'Hybrid (Roles + Direct Permissions + Wildcards)',
    'mode_rbac' => 'RBAC (Roles and Role Permissions only)',
    'mode_pbac' => 'PBAC (Direct User Permissions only)',
    'prompt_wildcards' => 'Do you want to enable Wildcards evaluation (e.g., orders.*)?',
    'prompt_pk' => 'Which primary key type do you prefer for ACL tables?',
    'prompt_user_fk' => 'Which key type does your users table use for user_id?',
    'prompt_run_migrations' => 'Would you like to run migrations right now?',
    'publishes_config' => 'Publishing configuration file...',
    'publishes_migrations' => 'Publishing dynamic migrations...',
    'install_success' => 'PMB ACL Package successfully installed and configured.',
    'unauthorized_role' => 'Unauthorized access. Required role(s): :roles',
    'unauthorized_permission' => 'Unauthorized access. Required permission: :permission',
];