<?php

namespace App\Domain\Communication\Notifiers;

use App\Domain\Communication\Contracts\GuardianNotifier;
use App\Domain\Student\Models\Guardian;
use App\Domain\Student\Models\Student;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Sends the absence notice as a WhatsApp Business API template message.
 * The access token, phone number id and template name come from the
 * client's .env (services.whatsapp). The template takes three text
 * parameters: the student's name, the date and the period.
 */
class WhatsAppGuardianNotifier implements GuardianNotifier
{
    private const ENDPOINT = 'https://graph.facebook.com/v21.0';

    /**
     * @param  array{token: string|null, phone_number_id: string|null, absence_template: string|null, language: string|null}  $config
     */
    public function __construct(private array $config) {}

    public function absent(Guardian $guardian, Student $student, CarbonInterface $date, string $when): void
    {
        if (blank($this->config['token']) || blank($this->config['phone_number_id']) || blank($this->config['absence_template'])) {
            throw new RuntimeException('WhatsApp is not configured for this client (WHATSAPP_TOKEN, WHATSAPP_PHONE_NUMBER_ID and WHATSAPP_ABSENCE_TEMPLATE).');
        }

        Http::withToken($this->config['token'])
            ->acceptJson()
            ->post(self::ENDPOINT."/{$this->config['phone_number_id']}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $guardian->phone,
                'type' => 'template',
                'template' => [
                    'name' => $this->config['absence_template'],
                    'language' => ['code' => $this->config['language'] ?: 'en'],
                    'components' => [[
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $student->name],
                            ['type' => 'text', 'text' => $date->format('j M Y')],
                            ['type' => 'text', 'text' => $when],
                        ],
                    ]],
                ],
            ])
            ->throw();
    }
}
