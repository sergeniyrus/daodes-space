<?php // app/Models/Message.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use GuzzleHttp\Client;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class Message extends Model
{
    protected $fillable = [
        'chat_id',
        'sender_id',
        'ipfs_cid',
        'reply_to_message_id',
        'topic_id',
        'message_type',
        'edited_at',
    ];

    public function chat()
    {
        return $this->belongsTo(Chat::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * Загружает строку вида "nonce|ciphertext" в IPFS.
     */
    public function uploadMessageToIPFS(string $fullPayload): string
    {
        try {
            $client = new Client([
                'base_uri' => rtrim(config('services.ipfs.gateway', 'https://daodes.space'), '/'),
                'timeout' => 60.0,
            ]);

            $response = $client->request('POST', '/api/v0/add', [
                'multipart' => [
                    [
                        'name'     => 'file',
                        'contents' => $fullPayload,
                        'filename' => 'message.txt',
                    ]
                ]
            ]);

            $data = json_decode($response->getBody(), true);
            if (!isset($data['Hash'])) {
                throw new Exception('IPFS did not return CID');
            }
            return $data['Hash'];
        } catch (Exception $e) {
            Log::error('IPFS upload error: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Получает полный payload из IPFS: "nonce|ciphertext"
     */
    public function getMessageFromIPFS(string $cid): string
    {
        if (!$cid) {
            return '';
        }

        $cacheKey = "ipfs:message:{$cid}";

        if (Cache::has($cacheKey)) {
            return (string) Cache::get($cacheKey);
        }

        try {
            $client = new Client([
                'base_uri' => rtrim(config('services.ipfs.gateway', 'https://daodes.space'), '/'),
                'connect_timeout' => 3.0,
                'timeout' => 10.0,
            ]);

            $response = $client->request('GET', "/ipfs/{$cid}");
            $payload = $response->getBody()->getContents();

            // CID неизменяемый: успешно полученный зашифрованный payload можно
            // безопасно повторно использовать при следующих открытиях чата.
            Cache::put($cacheKey, $payload, now()->addDays(7));

            return $payload;
        } catch (Exception $e) {
            Log::error('IPFS fetch error', ['cid' => $cid, 'error' => $e->getMessage()]);
            return '[Ошибка загрузки]';
        }
    }

    /**
     * Обновляет содержимое сообщения: загружает новый payload в IPFS и обновляет CID.
     */
    public function updateMessageContent(string $newFullPayload): string
    {
        $newCid = $this->uploadMessageToIPFS($newFullPayload);
        $this->ipfs_cid = $newCid;
        $this->save();
        return $newCid;
    }
// Сообщение, на которое отвечаем
public function replyToMessage()
{
    return $this->belongsTo(Message::class, 'reply_to_message_id');
}

// Ответы на это сообщение
public function replies()
{
    return $this->hasMany(Message::class, 'reply_to_message_id');
}


    public function attachments()
    {
        return $this->hasMany(MessageAttachment::class)->orderBy('position');
    }

    public function reactions()
    {
        return $this->hasMany(MessageReaction::class);
    }

    public function topic()
    {
        return $this->belongsTo(ChatTopic::class, 'topic_id');
    }


    public function mentions()
    {
        return $this->hasMany(MessageMention::class);
    }

}