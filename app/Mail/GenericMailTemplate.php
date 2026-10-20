<?php

namespace App\Mail;

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

        foreach ($this->extraAttachments as $attachment) {
            $message->attachData(
                $attachment['contents'],
                $attachment['filename'],
                ['mime' => $attachment['mime'] ?? 'application/octet-stream']
            );
        }

        return $message;
    }
}
