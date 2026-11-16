<?php

namespace App\Notifications;

use App\Models\Product;
use App\Models\Setting;
use App\Support\Quantity;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Product X is low on stock" alert: shown in the bell and on the Low Stock Alerts page.
 * Also emailed when Settings > Notifications > "Email low-stock alerts" is on.
 */
class LowStockNotification extends Notification
{
    public function __construct(public readonly Product $product)
    {
    }

    public function via(object $notifiable): array
    {
        return Setting::get('low_stock_email', false) ? ['database', 'mail'] : ['database'];
    }

    /** Stored in notifications.type, so alerts can be found without depending on the class name. */
    public function databaseType(object $notifiable): string
    {
        return 'low_stock';
    }

    public function toArray(object $notifiable): array
    {
        $p = $this->product;
        $unit = $p->unit?->abbreviation;
        $state = bccomp((string) $p->current_stock, '0', 3) <= 0 ? 'out of stock' : 'low on stock';

        return [
            'product_id' => $p->id,
            'product_name' => $p->name,
            'sku' => $p->sku,
            'current_stock' => (string) $p->current_stock,
            'minimum_stock' => (string) $p->minimum_stock,
            'unit' => $unit,
            'message' => sprintf('%s is %s: %s %s left (minimum %s).', $p->name, $state, Quantity::format($p->current_stock), $unit, Quantity::format($p->minimum_stock)),
            'url' => route('products.show', $p->id, false),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject("Low stock: {$this->product->name} ({$this->product->sku})")
            ->line($data['message'])
            ->action('View product', url($data['url']))
            ->line('Consider creating a purchase order to restock it.');
    }
}
