<?php

namespace App\Mail;

use App\Services\HelperService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GenericMailTemplate extends Mailable
{
    use Queueable, SerializesModels;

    public $data;

    public $adminMail;

    public $companyName;

    /**
     * Adjuntos en memoria: [['contents' => string, 'filename' => string, 'mime' => string]]
     *
     * OJO: el nombre NO puede ser `$attachments` ni `$rawAttachments`, porque
     * Mailable ya usa esas propiedades para sus propios adjuntos y Laravel las
     * procesaría con otro formato (de ahí el error "$options must be array").
     *
     * @var array<int, array{contents: string, filename: string, mime?: string}>
     */
    public $extraAttachments;

    /**
     * Si es true no se agrega la copia interna automática (CC).
     *
     * @var bool
     */
    public $skipInternalCopy = false;

    /**
     * @param  array  $data
     * @param  string  $adminMail
     * @param  string  $companyName
     * @param  array<int, array{contents: string, filename: string, mime?: string}>  $attachments
     */
    public function __construct($data, $adminMail, $companyName, array $attachments = [])
    {
        $this->data = $data;
        $this->adminMail = $adminMail;
        $this->companyName = $companyName;
        $this->extraAttachments = $attachments;
    }

    public function build()
    {
        $message = $this->from($this->adminMail, $this->companyName)
            ->subject($this->data['title'])
            ->view('mail-templates.mail-template')
            ->with($this->data);

        $internal = $this->internalCopyAddress();

        if ($internal !== null && ! $this->skipInternalCopy && ! $this->alreadySentTo($internal)) {
            $message->cc($internal);
        }

        foreach ($this->extraAttachments as $attachment) {
            $message->attachData(
                $attachment['contents'],
                $attachment['filename'],
                ['mime' => $attachment['mime'] ?? 'application/octet-stream']
            );
        }

        return $message;
    }

    /**
     * Dirección de control interno. Se toma de `info_email` y, si no está
     * configurada, se cae a `company_email`.
     */
    private function internalCopyAddress(): ?string
    {
        $address = HelperService::getSettingData('info_email')
            ?: (HelperService::getSettingData('company_email') ?: config('mail.from.address'));

        if (empty($address) || ! filter_var($address, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $address;
    }

    /**
     * Evita poner en CC una dirección que ya es destinataria del correo
     * (por ejemplo las copias internas que se envían con To).
     */
    private function alreadySentTo(string $address): bool
    {
        $recipients = array_merge(
            is_array($this->to) ? $this->to : [],
            is_array($this->cc) ? $this->cc : [],
            is_array($this->bcc) ? $this->bcc : []
        );

        foreach ($recipients as $recipient) {
            if (is_array($recipient)) {
                $recipient = $recipient['address'] ?? $recipient['email'] ?? '';
            } elseif (is_object($recipient) && method_exists($recipient, 'getEmail')) {
                $recipient = $recipient->getEmail();
            } elseif (is_object($recipient) && isset($recipient->address)) {
                $recipient = $recipient->address;
            }

            if (strcasecmp(trim((string) $recipient), $address) === 0) {
                return true;
            }
        }

        return false;
    }
}
