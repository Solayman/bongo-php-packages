<?php

declare(strict_types=1);

namespace Bongo\Packages\Wishlist;

use Bongo\Packages\Wishlist\Models\Wishlist as WishlistModel;
use Bongo\Packages\Wishlist\Models\WishlistItem as ItemModel;
use System\Database\Model;

class Wishlist
{
    protected ?int $userId = null;
    protected ?string $sessionId = null;
    protected string $listName;
    protected ?WishlistModel $model = null;
    /** @var ItemModel[]|null */
    protected ?array $cachedItems = null;
    protected ?int $cachedCount = null;

    /** @var bool In-memory fake mode */
    protected static bool $fake = false;
    /** @var array<string, array<string, mixed>> In-memory storage when fake is enabled */
    protected static array $fakeStorage = [];

    public function __construct(?int $userId = null, ?string $sessionId = null, ?string $name = null)
    {
        $this->userId = $userId;
        $this->sessionId = $sessionId;
        $this->listName = $name ?? (function_exists('config') ? (string) config('wishlist.default_name', 'default') : 'default');
    }

    /**
     * Create an instance for an authenticated user.
     */
    public static function forUser(Model|int|string $user, ?string $name = null): self
    {
        $id = $user instanceof Model ? (int) $user->getKey() : (int) $user;
        return new self($id, null, $name);
    }

    /**
     * Create an instance for a guest session.
     */
    public static function forSession(string $sessionId, ?string $name = null): self
    {
        return new self(null, $sessionId, $name);
    }

    /**
     * Create or retrieve default wishlist (auto-detects Auth user or session).
     */
    public static function make(?string $name = null): self
    {
        $userId = null;
        if (class_exists(\System\Auth\Auth::class) && method_exists(\System\Auth\Auth::class, 'id')) {
            $userId = \System\Auth\Auth::id();
        }

        $sessionId = null;
        if (!$userId && function_exists('session_id') && session_id() !== '') {
            $sessionId = session_id();
        }

        return new self($userId ? (int) $userId : null, $sessionId, $name);
    }

    /**
     * Switch list name.
     */
    public function name(string $name): self
    {
        $clone = clone $this;
        $clone->listName = $name;
        $clone->model = null;
        $clone->cachedItems = null;
        $clone->cachedCount = null;
        return $clone;
    }

    /**
     * Get or create the underlying database model.
     */
    public function getModel(): WishlistModel
    {
        if ($this->model !== null) {
            return $this->model;
        }

        $query = WishlistModel::query()->where('name', $this->listName);
        if ($this->userId !== null) {
            $query->where('user_id', $this->userId);
        } elseif ($this->sessionId !== null) {
            $query->where('session_id', $this->sessionId);
        } else {
            // Temporary anonymous session
            $this->sessionId = 'anon_' . bin2hex(random_bytes(8));
            $query->where('session_id', $this->sessionId);
        }

        $model = $query->first();
        if (!$model) {
            $model = WishlistModel::create([
                'user_id' => $this->userId,
                'session_id' => $this->sessionId,
                'name' => $this->listName,
                'share_token' => bin2hex(random_bytes(16)),
                'is_public' => false,
            ]);
        }

        $this->model = $model;
        return $this->model;
    }

    /**
     * Add an item to the wishlist.
     */
    public function add(mixed $item, int $quantity = 1, ?float $price = null, array $options = []): ItemModel
    {
        $this->cachedItems = null;
        $this->cachedCount = null;
        [$type, $id, $itemPrice] = $this->resolveItem($item);
        $finalPrice = $price ?? $itemPrice;

        if (self::$fake) {
            $key = $this->getFakeKey();
            $items = self::$fakeStorage[$key]['items'] ?? [];
            foreach ($items as $idx => $existing) {
                if ($existing['item_type'] === $type && (string) $existing['item_id'] === (string) $id) {
                    $items[$idx]['quantity'] += $quantity;
                    if ($finalPrice !== null) {
                        $items[$idx]['price'] = $finalPrice;
                    }
                    self::$fakeStorage[$key]['items'] = $items;
                    return new ItemModel($items[$idx]);
                }
            }

            $newItem = [
                'id' => count($items) + 1,
                'wishlist_id' => 1,
                'item_type' => $type,
                'item_id' => (string) $id,
                'quantity' => $quantity,
                'price' => $finalPrice,
                'options' => $options,
            ];
            $items[] = $newItem;
            self::$fakeStorage[$key]['items'] = $items;
            return new ItemModel($newItem);
        }

        $wishlist = $this->getModel();
        $existing = ItemModel::query()
            ->where('wishlist_id', $wishlist->id)
            ->where('item_type', $type)
            ->where('item_id', (string) $id)
            ->first();

        if ($existing) {
            $existing->quantity += $quantity;
            if ($finalPrice !== null) {
                $existing->price = $finalPrice;
            }
            if (!empty($options)) {
                $existing->options = $options;
            }
            $existing->save();
            return $existing;
        }

        return ItemModel::create([
            'wishlist_id' => $wishlist->id,
            'item_type' => $type,
            'item_id' => (string) $id,
            'quantity' => $quantity,
            'price' => $finalPrice,
            'options' => $options,
        ]);
    }

    /**
     * Remove an item from the wishlist.
     */
    public function remove(mixed $item): bool
    {
        $this->cachedItems = null;
        $this->cachedCount = null;
        [$type, $id] = $this->resolveItem($item);

        if (self::$fake) {
            $key = $this->getFakeKey();
            $items = self::$fakeStorage[$key]['items'] ?? [];
            $filtered = array_values(array_filter($items, function ($entry) use ($type, $id) {
                return !($entry['item_type'] === $type && (string) $entry['item_id'] === (string) $id);
            }));
            $removed = count($filtered) < count($items);
            self::$fakeStorage[$key]['items'] = $filtered;
            return $removed;
        }

        $wishlist = $this->getModel();
        $existing = ItemModel::query()
            ->where('wishlist_id', $wishlist->id)
            ->where('item_type', $type)
            ->where('item_id', (string) $id)
            ->first();

        if ($existing) {
            $existing->delete();
            return true;
        }

        return false;
    }

    /**
     * Check if an item exists in the wishlist.
     */
    public function has(mixed $item): bool
    {
        [$type, $id] = $this->resolveItem($item);

        if (self::$fake) {
            $key = $this->getFakeKey();
            $items = self::$fakeStorage[$key]['items'] ?? [];
            foreach ($items as $entry) {
                if ($entry['item_type'] === $type && (string) $entry['item_id'] === (string) $id) {
                    return true;
                }
            }
            return false;
        }

        // Check instance cache first to eliminate DB roundtrip
        if ($this->cachedItems !== null) {
            foreach ($this->cachedItems as $entry) {
                if ($entry->item_type === $type && (string) $entry->item_id === (string) $id) {
                    return true;
                }
            }
            return false;
        }

        $wishlist = $this->getModel();
        return ItemModel::query()
            ->where('wishlist_id', $wishlist->id)
            ->where('item_type', $type)
            ->where('item_id', (string) $id)
            ->exists();
    }

    /**
     * Total number of items in the wishlist.
     */
    public function count(): int
    {
        if (self::$fake) {
            $key = $this->getFakeKey();
            return count(self::$fakeStorage[$key]['items'] ?? []);
        }

        if ($this->cachedItems !== null) {
            return count($this->cachedItems);
        }

        if ($this->cachedCount !== null) {
            return $this->cachedCount;
        }

        $wishlist = $this->getModel();
        $this->cachedCount = ItemModel::query()->where('wishlist_id', $wishlist->id)->count();
        return $this->cachedCount;
    }

    /**
     * Return all items.
     *
     * @return ItemModel[]
     */
    public function items(): array
    {
        if (self::$fake) {
            $key = $this->getFakeKey();
            $items = self::$fakeStorage[$key]['items'] ?? [];
            return array_map(fn($arr) => new ItemModel($arr), $items);
        }

        if ($this->cachedItems !== null) {
            return $this->cachedItems;
        }

        $wishlist = $this->getModel();
        $this->cachedItems = ItemModel::query()->where('wishlist_id', $wishlist->id)->get();
        $this->cachedCount = count($this->cachedItems);
        return $this->cachedItems;
    }

    /**
     * Clear all items in the wishlist.
     */
    public function clear(): void
    {
        $this->cachedItems = null;
        $this->cachedCount = 0;

        if (self::$fake) {
            $key = $this->getFakeKey();
            self::$fakeStorage[$key]['items'] = [];
            return;
        }

        $wishlist = $this->getModel();
        ItemModel::query()->where('wishlist_id', $wishlist->id)->delete();
    }

    /**
     * Get or create a unique share token for public sharing.
     */
    public function shareToken(): string
    {
        if (self::$fake) {
            return 'fake_token_' . md5($this->getFakeKey());
        }

        $wishlist = $this->getModel();
        if (empty($wishlist->share_token)) {
            $wishlist->share_token = bin2hex(random_bytes(16));
            $wishlist->save();
        }
        return (string) $wishlist->share_token;
    }

    /**
     * Generate full public shareable URL.
     */
    public function getShareUrl(): string
    {
        $prefix = function_exists('config') ? (string) config('wishlist.share_url_prefix', '/wishlist/shared/') : '/wishlist/shared/';
        return rtrim($prefix, '/') . '/' . $this->shareToken();
    }

    /**
     * Make wishlist public.
     */
    public function makePublic(): self
    {
        if (!self::$fake) {
            $wishlist = $this->getModel();
            $wishlist->is_public = true;
            $wishlist->save();
        }
        return $this;
    }

    /**
     * Make wishlist private.
     */
    public function makePrivate(): self
    {
        if (!self::$fake) {
            $wishlist = $this->getModel();
            $wishlist->is_public = false;
            $wishlist->save();
        }
        return $this;
    }

    /**
     * Check if public.
     */
    public function isPublic(): bool
    {
        if (self::$fake) {
            return false;
        }
        return (bool) $this->getModel()->is_public;
    }

    /**
     * Find a wishlist by its public share token.
     */
    public static function findByShareToken(string $token): ?self
    {
        $model = WishlistModel::query()->where('share_token', $token)->first();
        if (!$model) {
            return null;
        }

        $instance = new self($model->user_id ? (int) $model->user_id : null, $model->session_id, $model->name);
        $instance->model = $model;
        return $instance;
    }

    /**
     * Merge items from a guest session into an authenticated user's wishlist(s).
     */
    public static function mergeGuestToUser(string $sessionId, int|string $userId): void
    {
        $userId = (int) $userId;
        $guestLists = WishlistModel::query()->where('session_id', $sessionId)->get();

        foreach ($guestLists as $guestList) {
            $userList = WishlistModel::query()
                ->where('user_id', $userId)
                ->where('name', $guestList->name)
                ->first();

            if ($userList) {
                // Merge items into existing user list with single pre-query (eliminates N+1 loop)
                $guestItems = ItemModel::query()->where('wishlist_id', $guestList->id)->get();
                $userItems = ItemModel::query()->where('wishlist_id', $userList->id)->get();
                $existingMap = [];
                foreach ($userItems as $uItem) {
                    $existingMap[$uItem->item_type . ':' . $uItem->item_id] = $uItem;
                }

                foreach ($guestItems as $guestItem) {
                    $key = $guestItem->item_type . ':' . $guestItem->item_id;
                    if (isset($existingMap[$key])) {
                        $existingItem = $existingMap[$key];
                        $existingItem->quantity += $guestItem->quantity;
                        $existingItem->save();
                        $guestItem->delete();
                    } else {
                        $guestItem->wishlist_id = $userList->id;
                        $guestItem->save();
                        $existingMap[$key] = $guestItem;
                    }
                }
                $guestList->delete();
            } else {
                // Adopt guest list to user
                $guestList->user_id = $userId;
                $guestList->session_id = null;
                $guestList->save();
            }
        }
    }

    /**
     * Enable or reset in-memory fake mode.
     */
    public static function fake(): void
    {
        self::$fake = true;
        self::$fakeStorage = [];
    }

    /**
     * Disable fake mode.
     */
    public static function stopFaking(): void
    {
        self::$fake = false;
        self::$fakeStorage = [];
    }

    /**
     * Resolve item into [type, id, price].
     *
     * @return array{0: string, 1: string, 2: ?float}
     */
    protected function resolveItem(mixed $item): array
    {
        if ($item instanceof Model) {
            $price = isset($item->price) && is_numeric($item->price) ? (float) $item->price : null;
            return [get_class($item), (string) $item->getKey(), $price];
        }

        if (is_array($item)) {
            $type = (string) ($item['item_type'] ?? $item['type'] ?? 'product');
            $id = (string) ($item['item_id'] ?? $item['id'] ?? '1');
            $price = isset($item['price']) && is_numeric($item['price']) ? (float) $item['price'] : null;
            return [$type, $id, $price];
        }

        return ['product', (string) $item, null];
    }

    protected function getFakeKey(): string
    {
        return ($this->userId ? 'u_' . $this->userId : 's_' . ($this->sessionId ?? 'anon')) . '_' . $this->listName;
    }
}
