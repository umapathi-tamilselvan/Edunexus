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
 * The access token, phone number id and template names come from the
 * client's .env (services.whatsapp).
 */
class WhatsAppGuardianNotifier implements GuardianNotifier
{
    private const ENDPOINT = 'https://graph.facebook.com/v21.0';

    /**
     * @param  array{token: string|null, phone_number_id: string|null, absence_template: string|null, otp_template: string|null, language: string|null}  $config
     */
    public function __construct(private array $config) {}

    public function absent(Guardian $guardian, Student $student, CarbonInterface $date, string $when): void
    {
        $this->send($guardian, (string) $this->config['absence_template'], [[
            'type' => 'body',
            'parameters' => [
                ['type' => 'text', 'text' => $student->name],
                ['type' => 'text', 'text' => $date->format('j M Y')],
                ['type' => 'text', 'text' => $when],
            ],
        ]]);
    }

    /**
     * An authentication template: the code goes in the body and on the copy-code button.
     */
    public function otp(Guardian $guardian, string $code): void
    {
        $this->send($guardian, (string) $this->config['otp_template'], [
            ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $code]]],
            ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $code]]],
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $components
     */
    private function send(Guardian $guardian, string $template, array $components): void
    {
        if (blank($this->config['token']) || blank($this->config['phone_number_id']) || blank($template)) {
            throw new RuntimeException('WhatsApp is not configured for this client (WHATSAPP_TOKEN, WHATSAPP_PHONE_NUMBER_ID and the template names).');
        }

        Http::withToken($this->config['token'])
            ->acceptJson()
            ->post(self::ENDPOINT."/{$this->config['phone_number_id']}/messages", [
                'messaging_product' => 'whatsapp',
                'to' => $guardian->phone,
                'type' => 'template',
                'template' => [
                    'name' => $template,
                    'language' => ['code' => $this->config['language'] ?: 'en'],
                    'components' => $components,
                ],
            ])
            ->throw();
    }
}
