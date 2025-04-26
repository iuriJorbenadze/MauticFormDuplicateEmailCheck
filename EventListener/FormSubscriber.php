<?php
// plugins/DuplicateCheckBundle/EventListener/FormSubscriber.php

namespace MauticPlugin\DuplicateCheckBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Mautic\FormBundle\Event\FormBuilderEvent;
use Mautic\FormBundle\Event\ValidationEvent;
use Mautic\FormBundle\FormEvents;
use Mautic\LeadBundle\Model\LeadModel;
// Potentially needed if logging issues:
// use Psr\Log\LoggerInterface;

class FormSubscriber implements EventSubscriberInterface
{
    public const CUSTOM_EMAIL_VALIDATION_EVENT = 'plugin.duplicatecheck.validate_email';
    private const REDIRECT_FLAG_ATTRIBUTE     = '_plugin_duplicate_check_redirect_url';
    private const REDIRECT_TARGET_URL         = 'https://vyhraj.cz/uzivatel-je-jiz-zaregistrovany/';
    
    // List of form field keys to ignore when building the redirect URL (system fields that should not be included as user parameters)
    private const EXCLUDED_KEYS = ['email', 'formId', 'formName', 'return', 'messenger', 'form_submitted'];

    // --- Define Excluded Form IDs ---
    // Add the Mautic Form IDs you want to EXCLUDE from the duplicate check here.
    private const EXCLUDED_FORM_IDS = [
        26 // Your excluded form ID
        // Example: 5, 12 // Add your specific form IDs here
    ];
    // ------------------------------

    private RequestStack $requestStack;
    private LeadModel $leadModel;
    // Optional: Inject logger for debugging issues
    // private ?LoggerInterface $logger;

    // Update constructor if adding logger
    public function __construct(
        RequestStack $requestStack,
        LeadModel $leadModel
        // LoggerInterface $logger = null // Uncomment if using logger
    ) {
        $this->requestStack = $requestStack;
        $this->leadModel    = $leadModel;
        // $this->logger    = $logger; // Uncomment if using logger
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::FORM_ON_BUILD           => ['onFormBuild', 0],
            self::CUSTOM_EMAIL_VALIDATION_EVENT => ['onValidateEmail', 0],
            KernelEvents::RESPONSE              => ['onKernelResponse', -10],
        ];
    }

    // --- UPDATED METHOD ---
    public function onFormBuild(FormBuilderEvent $event): void
    {
        $currentFormId = null;
        $request = $this->requestStack->getCurrentRequest();

        // Try to get the form ID specifically from POST data during submission
        // Mautic forms submit data within a 'mauticform' array
        if ($request && $request->request->has('mauticform')) {
            $mauticFormData = $request->request->all('mauticform');
            // Check if 'formId' exists within the submitted form data
            if (isset($mauticFormData['formId']) && is_numeric($mauticFormData['formId'])) {
                $currentFormId = (int) $mauticFormData['formId'];
            }
        }

        // If we successfully determined the form ID from the submission request...
        if ($currentFormId !== null) {
            // ...check if this form ID is in our exclusion list
            if (in_array($currentFormId, self::EXCLUDED_FORM_IDS)) {
                // Optional: Log exclusion
                // $this->logger?->info("[DuplicateCheck] Form ID {$currentFormId} from request is excluded. Skipping validator attachment.");
                return; // <-- Do not add the validator for this submitted form
            }
        }
        // Else: We couldn't determine the form ID from the request
        // (e.g., form is being rendered via GET, not submitted via POST)
        // OR the form ID was found and is NOT excluded.
        // In these cases, we proceed to add the validator.
        // The actual check logic in onValidateEmail will only run if a value is submitted anyway.

        // Optional: Log attachment
        // $this->logger?->debug("[DuplicateCheck] Attaching validator. Form ID from request: " . ($currentFormId ?? 'N/A'));

        // Add the validator hook
        $event->addValidator(
            'duplicatecheck.email_validator_hook',
            [
                'eventName' => self::CUSTOM_EMAIL_VALIDATION_EVENT,
                'fieldType' => 'email', // Only applies to email fields
            ]
        );
    }
    // --- END UPDATED METHOD ---


    public function onValidateEmail(ValidationEvent $event): void
    {
        // This method is now only called for forms where the validator WAS attached
        // (i.e., forms NOT in the exclusion list)
        $field = $event->getField();
        if ($field->getType() !== 'email') {
            return;
        }

        $isDuplicate = false;
        $emailValue = $event->getValue();

        if ($emailValue && is_string($emailValue)) {
            $emailValue = strtolower(trim($emailValue));
            if (filter_var($emailValue, FILTER_VALIDATE_EMAIL)) {
                $existingLead = $this->leadModel->getRepository()->findOneBy(['email' => $emailValue]);
                $isDuplicate = ($existingLead !== null);
            }
        }

        if ($isDuplicate) {
            $event->failedValidation('This email address already exists in our system.');
            $request = $this->requestStack->getCurrentRequest();
            if ($request) {
                // Build dynamic redirect URL by appending parameters from submitted mauticform data
                $baseRedirectUrl = self::REDIRECT_TARGET_URL;
                $mauticFormData = $request->request->get('mauticform', []);

                $params = [];

                // Ignore these system fields
                foreach ($mauticFormData as $key => $value) {
                    if (!in_array($key, self::EXCLUDED_KEYS, true) && !empty($value)) {
                        $params[$key] = $value;
                    }
                }

                if (!empty($params)) {
                    $queryString = http_build_query($params);
                    $redirectUrlWithParams = $baseRedirectUrl . (strpos($baseRedirectUrl, '?') === false ? '?' : '&') . $queryString;
                } else {
                    $redirectUrlWithParams = $baseRedirectUrl;
                }

                $request->attributes->set(self::REDIRECT_FLAG_ATTRIBUTE, $redirectUrlWithParams);
            }
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
       // This method remains unchanged
       if (!$event->isMainRequest()) { return; }
       $request = $event->getRequest();
       if ($request->attributes->has(self::REDIRECT_FLAG_ATTRIBUTE)) {
           $redirectUrl = $request->attributes->get(self::REDIRECT_FLAG_ATTRIBUTE);
           $html = <<<HTML
<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Redirecting...</title></head><body>
<script type="text/javascript">if (window.top && window.top.location.href !== "$redirectUrl") {window.top.location.href = "$redirectUrl";} else if (window.location.href !== "$redirectUrl") {window.location.href = "$redirectUrl";}</script>
<noscript><p>This email is already registered. <a href="$redirectUrl">Click here to continue</a>.</p><meta http-equiv="refresh" content="0;url=$redirectUrl" /></noscript>
</body></html>
HTML;
           $response = new Response($html, 200, ['Content-Type' => 'text/html']);
           $event->setResponse($response);
           $request->attributes->remove(self::REDIRECT_FLAG_ATTRIBUTE);
       }
    }
}