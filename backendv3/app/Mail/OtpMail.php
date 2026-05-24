<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * OTP認証コードメール
 */
class OtpMail extends Mailable
{
    /**
     * コンストラクタ
     */
    public function __construct(
        private readonly string $code,
    ) {}

    /**
     * メール件名・送信者定義
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '【認証コード】' . config('app.name'),
        );
    }

    /**
     * メール本文定義
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.otp',
            with: [
                'code'           => $this->code,
                'expiresMinutes' => 10,
            ],
        );
    }
}
