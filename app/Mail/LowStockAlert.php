<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LowStockAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public int $productId,
        public string $productName,
        public int $remainingStock,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Low stock alert: '.$this->productName,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.low-stock-alert',
        );
    }
}
