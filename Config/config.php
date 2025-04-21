<?php
// plugins/DuplicateCheckBundle/Config/config.php

use MauticPlugin\DuplicateCheckBundle\EventListener\FormSubscriber;

return [
    'name'        => 'Duplicate Email Check & Redirect',
    // Update description
    'description' => 'Checks for duplicate lead emails in the database on form submission, prevents saving, and redirects if duplicate found.',
    'version'     => '1.3.0', // Version bump for new feature
    'author'      => 'Your Name',
    'mautic_version' => '^5.0',

    'services' => [
        'events' => [
            'plugin.duplicatecheck.event_listener.form_subscriber' => [
                'class'     => FormSubscriber::class,
                'arguments' => [
                    'request_stack',
                    'mautic.lead.model.lead', // <-- Inject the LeadModel service
                ],
                'tags'      => ['kernel.event_subscriber'],
            ],
        ],
    ],
];