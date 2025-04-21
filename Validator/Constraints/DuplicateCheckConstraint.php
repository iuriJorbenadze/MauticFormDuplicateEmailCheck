<?php
// plugins/DuplicateCheckBundle/Validator/Constraints/DuplicateCheckConstraint.php

namespace MauticPlugin\DuplicateCheckBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;

/**
 * @Annotation
 * @Target({"PROPERTY", "METHOD", "ANNOTATION"})
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
class DuplicateCheckConstraint extends Constraint
{
    public string $message = 'This email address already exists in our system (MVP check).';

    /**
     * Returns the service name of the validator.
     *
     * The service must be defined in the container.
     */
    public function validatedBy(): string
    {
        // This alias MUST match the alias defined in config.php for the validator service
        return 'plugin.duplicatecheck.validator.duplicate_check';
    }

    /**
     * {@inheritdoc}
     */
    public function getTargets(): string|array
    {
        return self::PROPERTY_CONSTRAINT;
    }
}
