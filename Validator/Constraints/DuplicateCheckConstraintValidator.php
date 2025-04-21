<?php
// plugins/DuplicateCheckBundle/Validator/Constraints/DuplicateCheckConstraintValidator.php

namespace MauticPlugin\DuplicateCheckBundle\Validator\Constraints;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Psr\Log\LoggerInterface; // Optional: for logging

class DuplicateCheckConstraintValidator extends ConstraintValidator
{
    // Optional: Inject logger if needed for debugging real implementation later
    // private LoggerInterface $logger;
    // public function __construct(LoggerInterface $logger) {
    //     $this->logger = $logger;
    // }

    /**
     * Checks if the passed value is valid.
     *
     * @param mixed $value The value that should be validated
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DuplicateCheckConstraint) {
            throw new UnexpectedTypeException($constraint, DuplicateCheckConstraint::class);
        }

        // If the value is null or empty, skip validation (usually handled by NotBlank constraint)
        if (null === $value || '' === $value) {
            return;
        }

        // --- MVP Logic: Always assume it's a duplicate ---
        $isDuplicate = true;
        // -------------------------------------------------

        // --- Placeholder for real DB check logic ---
        /*
        if (is_string($value)) {
            // TODO: Implement database check here using $value (the email string)
            // Inject Doctrine\DBAL\Connection 'database_connection' via constructor
            // Or inject LeadRepository 'mautic.lead.model.lead' via constructor
            // $isDuplicate = $this->checkEmailInDatabase($value);
        } else {
            // If the value isn't a string, it can't be a duplicate email in this context
            $isDuplicate = false;
        }
        */
        // --- End Placeholder ---

        if ($isDuplicate) {
            // Add a violation if the email is considered a duplicate
            $this->context->buildViolation($constraint->message)
                // ->setParameter('{{ value }}', $this->formatValue($value)) // Optional: include value in message
                ->addViolation();
        }
    }

    // --- Placeholder for real DB check method ---
    /*
    private function checkEmailInDatabase(string $email): bool
    {
        // Example using LeadRepository (needs injection)
        // $existingLead = $this->leadRepository->findOneBy(['email' => $email]);
        // return ($existingLead !== null);

        // Example using DBAL (needs injection)
        // $qb = $this->connection->createQueryBuilder();
        // $qb->select('COUNT(l.id)')
        //    ->from(MAUTIC_TABLE_PREFIX . 'leads', 'l')
        //    ->where($qb->expr()->eq('l.email', ':email'))
        //    ->setParameter('email', $email);
        // $count = (int) $qb->executeQuery()->fetchOne();
        // return $count > 0;

        return true; // Keep as true for MVP
    }
    */
    // --- End Placeholder ---
}
