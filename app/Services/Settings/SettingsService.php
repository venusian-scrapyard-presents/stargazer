<?php

namespace App\Services\Settings;

use App\Enums\Outcome;
use App\GlobalVariables;
use App\NutsAndBolts\CacheStore;
use App\NutsAndBolts\NasaKey;
use App\NutsAndBolts\RateLimit;
use App\NutsAndBolts\StartPage;
use App\RouteMap;
use App\Runner\Controllers\StargazerController;
use ReflectionException;
use Surface\Contracts\Windows\Primitives\Align;
use Surface\Contracts\Windows\Primitives\TKButton;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKDropdown;
use Surface\Contracts\Windows\Primitives\TKLabel;
use Surface\Contracts\Windows\Primitives\TKRow;
use Surface\Contracts\Windows\Primitives\TKTextInput;
use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\Styling\FontWeight;

/**
 * The NASA key and what is left of its hourly quota, the page the app opens on, and the
 * cache store.
 */
class SettingsService
{
    /** Seconds between rereads of the quota while Settings shows. */
    protected const float RATE_EVERY = 1.0;

    /** @var list<string> The cache store behind each dropdown option, in order. */
    protected const array STORES = [CacheStore::FILE, CacheStore::REDIS];

    protected ?TKLabel $rate = null;

    protected float $rate_read_at = 0.0;

    protected ?TKDropdown $store = null;

    protected ?TKRow $redis = null;

    protected ?TKTextInput $redis_host = null;

    protected ?TKTextInput $redis_port = null;

    protected ?TKTextInput $redis_password = null;

    protected ?TKTextInput $redis_database = null;

    protected ?TKButton $apply_store = null;

    protected ?TKLabel $store_note = null;

    protected ?TKTextInput $key = null;

    protected ?TKButton $save_key = null;

    protected ?TKDropdown $start_page = null;

    /** @var list<string> The start-page choice behind each dropdown option, in order. */
    protected array $choices = [];

    public function key(): string
    {
        return 'settings';
    }

    public function mounted(TKColumn $root): void
    {
        $root->label('heading', 'Settings')->setFont(new FontSpec(22, FontWeight::BOLD));

        $root->label('key-heading', 'NASA API key')->setFont(new FontSpec(15, FontWeight::BOLD));
        $key_row = $root->row('key-form', 10);
        $this->key = $key_row->textInput('key', '');
        $this->key->minSize(360, 0);
        $this->save_key = $key_row->button('save-key', 'Save key');
        $this->rate = $root->label('rate', RateLimit::describe(null));

        $root->label('start-heading', 'Start page')->setFont(new FontSpec(15, FontWeight::BOLD));
        $start_row = $root->row('start-form', 10);
        $start_row->label('start-label', 'Open on');
        $this->start_page = $start_row->dropdown('start-page', ['Last opened']);
        $this->start_page->minSize(220, 0);

        $root->label('cache-heading', 'Cache')->setFont(new FontSpec(15, FontWeight::BOLD));
        $store_row = $root->row('cache-form', 10);
        $store_row->label('store-label', 'Store');
        $this->store = $store_row->dropdown('store', ['File', 'Redis']);
        $this->store->minSize(160, 0);
        $this->apply_store = $store_row->button('apply-store', 'Apply');

        $this->redis = $root->row('redis-form', 10);
        $this->redis->label('host-label', 'Host');
        $this->redis_host = $this->redis->textInput('host', '', CacheStore::REDIS_DEFAULTS['host']);
        $this->redis_host->minSize(180, 0);
        $this->redis->label('port-label', 'Port');
        $this->redis_port = $this->redis->textInput('port', '', (string) CacheStore::REDIS_DEFAULTS['port']);
        $this->redis_port->minSize(70, 0);
        $this->redis->label('password-label', 'Password');
        $this->redis_password = $this->redis->textInput('password', '', 'none', secret: true);
        $this->redis_password->minSize(140, 0);
        $this->redis->label('database-label', 'Database');
        $this->redis_database = $this->redis->textInput('database', '', (string) CacheStore::REDIS_DEFAULTS['database']);
        $this->redis_database->minSize(50, 0);
        $this->redis->hide();
        $this->store_note = $root->label('store-note', '');
        $this->store_note->setWrap(true);

        // The credit sits at the bottom, centred: an empty column takes the spare height above it.
        $root->column('spacer')->fill(false, true);
        $root->label('credit', 'Made with ADHD By Project Saturn Studios, LLC, © 2026')->align(Align::CENTER);
    }

    /**
     * Fill the form from what is stored: the key, and the start-page options (Last opened,
     * then each sidebar route) with the stored choice selected.
     * @throws ReflectionException
     */
    public function load(): void
    {
        $bag = GlobalVariables::make();
        $this->key->setValue(NasaKey::stored() ?? NasaKey::DEFAULT);

        $labels = ['Last opened'];
        $this->choices = [StartPage::LAST_RECENT];
        foreach (RouteMap::routes()->keys() as $route) {
            /** @var StargazerController $controller */
            $controller = $bag->controllers()[$route];
            $labels[] = $controller->title();
            $this->choices[] = $route;
        }

        $selected = array_search(StartPage::choice(), $this->choices, true);
        $this->start_page->setOptions($labels)->select($selected === false ? 0 : $selected);

        $stored = CacheStore::stored();
        $this->store->select((int) array_search($stored['driver'], self::STORES, true));
        $this->redis_host->setValue($stored['host']);
        $this->redis_port->setValue((string) $stored['port']);
        $this->redis_password->setValue($stored['password']);
        $this->redis_database->setValue((string) $stored['database']);
        $this->redis->setVisible($stored['driver'] === CacheStore::REDIS);
        $this->noteStore();
        $this->showRate();
    }

    /**
     * While Settings shows, the quota line follows the cache: any page's request may land.
     * @throws ReflectionException
     */
    public function tick(): void
    {
        if (microtime(true) - $this->rate_read_at >= self::RATE_EVERY) {
            $this->showRate();
        }
    }

    public function isStore(string $uuid): bool
    {
        return $uuid === $this->store?->uuid();
    }

    public function isApplyStore(string $uuid): bool
    {
        return $uuid === $this->apply_store?->uuid();
    }

    /** The Redis fields show while Redis is picked. */
    public function chooseStore(int $index): bool
    {
        $this->redis->setVisible((self::STORES[$index] ?? CacheStore::FILE) === CacheStore::REDIS);

        return true;
    }

    /**
     * Switch the app onto the picked store. Redis must answer on the fields first; when it
     * does not, the app stays where it was and the note says why.
     * @throws ReflectionException
     */
    public function applyStore(): bool
    {
        $settings = CacheStore::normalize([
            'driver' => self::STORES[$this->store->selected()] ?? CacheStore::FILE,
            'host' => $this->redis_host->value(),
            'port' => $this->redis_port->value(),
            'password' => $this->redis_password->value(),
            'database' => $this->redis_database->value(),
        ]);

        if ($settings['driver'] === CacheStore::REDIS && ! is_null($why = CacheStore::probe($settings))) {
            $message = "Redis at {$settings['host']}:{$settings['port']} did not answer ({$why}); the cache stays on ".ucfirst(CacheStore::active()).'.';
            $this->store_note->setText($message);
            GlobalVariables::make()->status_bar->settle($this->key(), Outcome::FAILED, $message);

            return true;
        }

        CacheStore::save($settings);
        $this->redis_host->setValue($settings['host']);
        $this->redis_port->setValue((string) $settings['port']);
        $this->redis_database->setValue((string) $settings['database']);
        $this->noteStore();
        $this->showRate();
        $this->settled($settings['driver'] === CacheStore::REDIS
            ? "Caching to Redis at {$settings['host']}:{$settings['port']}, database {$settings['database']}."
            : 'Caching to file.');

        return true;
    }

    public function isSaveKey(string $uuid): bool
    {
        return $uuid === $this->save_key?->uuid();
    }

    public function isKeyInput(string $uuid): bool
    {
        return $uuid === $this->key?->uuid();
    }

    public function isStartPage(string $uuid): bool
    {
        return $uuid === $this->start_page?->uuid();
    }

    /**
     * Store the field's key (DEMO_KEY when it is empty) and switch the running client to it.
     * @throws ReflectionException
     */
    public function saveKey(): bool
    {
        $key = trim($this->key->value());
        $key = $key === '' ? NasaKey::DEFAULT : $key;
        NasaKey::save($key);
        $this->key->setValue($key);
        $this->showRate();
        $this->settled("NASA key saved; new requests use {$key}.");

        return true;
    }

    /**
     * @throws ReflectionException
     */
    public function chooseStartPage(int $index): bool
    {
        $choice = $this->choices[$index] ?? StartPage::LAST_RECENT;
        StartPage::choose($choice);
        $this->settled('Stargazer will open on '.$this->start_page->selectedOption().'.');

        return true;
    }

    /**
     * The quota last reported for the key the app uses now.
     * @throws ReflectionException
     */
    protected function showRate(): void
    {
        $this->rate_read_at = microtime(true);

        try {
            $text = RateLimit::describe(app(RateLimit::class)->reading((string) (config('nasa.api_key') ?: NasaKey::DEFAULT)));
        } catch (\Throwable $e) {
            $text = "The quota could not be read from the cache ({$e->getMessage()}).";
        }

        if ($this->rate->text() !== $text) {
            $this->rate->setText($text);
        }
    }

    protected function noteStore(): void
    {
        $this->store_note->setText(is_null(CacheStore::$unreachable)
            ? 'Caching to '.ucfirst(CacheStore::active()).'.'
            : 'Redis did not answer at launch ('.CacheStore::$unreachable.'); caching to File for this run.');
    }

    protected function settled(string $message): void
    {
        GlobalVariables::make()->status_bar->settle($this->key(), Outcome::DONE, $message);
    }
}
