<?php

namespace App\Services\Settings;

use App\Actions\Work\DispatchController;
use App\GlobalVariables;
use App\NutsAndBolts\AppIcon;
use App\NutsAndBolts\Links;
use App\NutsAndBolts\NasaKey;
use App\NutsAndBolts\StartPage;
use ReflectionException;
use Surface\Contracts\Windows\Primitives\Align;
use Surface\Contracts\Windows\Primitives\ImageScaling;
use Surface\Contracts\Windows\Primitives\TKButton;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKTextInput;
use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\Styling\FontWeight;
use Surface\Contracts\Windows\Styling\TextAlignment;
use Surface\NutsAndBolts\Color;
use Throwable;

/**
 * The first-run form: asks for the api.nasa.gov key, stores it, then opens the start page.
 */
class KeyService
{
    /** api.nasa.gov's sign-up form; a key arrives by email. */
    public const string SIGNUP = 'https://api.nasa.gov/';

    /** The utm_campaign every link on this screen carries. */
    public const string CAMPAIGN = 'welcome';

    protected ?TKTextInput $key = null;

    protected ?TKButton $demo = null;

    protected ?TKButton $save = null;

    public function mounted(TKColumn $root): void
    {
        $root->column('above')->fill(false, true);
        $panel = $root->column('panel', 12);
        $panel->align(Align::CENTER, Align::CENTER);
        $root->column('below')->fill(false, true);
        $this->footer($root);

        // The 128 px copy, as a plain file: an image shows at its file's size, and a packaged app's
        // own files are phar:// paths the toolkit cannot open. No icon beats no welcome screen.
        $icon = AppIcon::path();
        if (! is_null($icon)) {
            try {
                $panel->image('icon', $icon)
                    ->setScaling(ImageScaling::FIT)
                    ->align(Align::CENTER);
            } catch (Throwable) {
                // A refused mint adopts nothing; this covers a toolkit that adopted first.
                $panel->view('icon')?->remove();
            }
        }
        $panel->label('heading', 'Welcome to Stargazer')
            ->setFont(new FontSpec(26, FontWeight::BOLD))
            ->setAlignment(TextAlignment::CENTER)
            ->align(Align::CENTER);
        $panel->label('about', "Browse NASA's open data: the Astronomy Picture of the Day, Earth from DSCOVR, space weather, near-Earth objects, satellites and more.")
            ->setWrap(true)
            ->setAlignment(TextAlignment::CENTER)
            ->minSize(460, 0);
        $panel->link('site', 'Visit the Stargazer website', Links::site(self::CAMPAIGN, 'website'))
            ->setAlignment(TextAlignment::CENTER)
            ->align(Align::CENTER);
        $panel->label('ask', 'Stargazer needs an api.nasa.gov key. Pick one to begin.')
            ->setFont(new FontSpec(15, FontWeight::BOLD))
            ->setWrap(true)
            ->setAlignment(TextAlignment::CENTER)
            ->minSize(460, 0);

        $panel->label('demo-heading', 'Just looking around?')
            ->setFont(new FontSpec(15, FontWeight::BOLD))
            ->setAlignment(TextAlignment::CENTER)
            ->align(Align::CENTER);
        $panel->label('demo-about', "Start now with NASA's shared DEMO_KEY. It has low hourly and daily limits.")
            ->setWrap(true)
            ->setAlignment(TextAlignment::CENTER)
            ->minSize(460, 0);
        $start = $panel->row('start');
        $start->align(Align::CENTER, Align::CENTER);
        $this->demo = $start->button('demo', 'Start with DEMO_KEY');

        $panel->separator('rule');

        $panel->label('own-heading', 'Have your own key?')
            ->setFont(new FontSpec(15, FontWeight::BOLD))
            ->setAlignment(TextAlignment::CENTER)
            ->align(Align::CENTER);
        $form = $panel->row('form', 10);
        $form->align(Align::CENTER, Align::CENTER);
        $this->key = $form->textInput('key', '', 'Paste your key here');
        $this->key->minSize(360, 0);
        $this->save = $form->button('save', 'Save key');
        $panel->link('signup', 'No key yet? Get a free one at api.nasa.gov. It arrives by email.', self::SIGNUP)
            ->setAlignment(TextAlignment::CENTER)
            ->align(Align::CENTER);

        $panel->label('later', 'You can change the key later in Settings.')
            ->setTextColor(Color::hex('#8a8a8a'))
            ->setAlignment(TextAlignment::CENTER)
            ->align(Align::CENTER);
    }

    /** The credit line along the bottom, with the studio's name as a link. */
    protected function footer(TKColumn $root): void
    {
        $footer = $root->row('footer', 4);
        $footer->align(Align::CENTER, Align::CENTER);
        $grey = Color::hex('#8a8a8a');
        $footer->label('made', 'Made with ADHD by')->setTextColor($grey);
        // In a row an AppKit link measures as zero width without a floor.
        $footer->link('studio', 'Project Saturn Studios', Links::studio(self::CAMPAIGN, 'made-with-adhd'))
            ->minSize(150, 0);
        $footer->label('year', '© '.date('Y'))->setTextColor($grey);
    }

    public function isDemo(string $uuid): bool
    {
        return $uuid === $this->demo?->uuid();
    }

    public function isSave(string $uuid): bool
    {
        return $uuid === $this->save?->uuid();
    }

    public function isKeyInput(string $uuid): bool
    {
        return $uuid === $this->key?->uuid();
    }

    /**
     * Store what the field holds (DEMO_KEY when it is empty), then open the start page.
     * @throws ReflectionException
     */
    public function save(): bool
    {
        $key = trim($this->key->value());

        return $this->start($key === '' ? NasaKey::DEFAULT : $key);
    }

    /**
     * Store DEMO_KEY, then open the start page.
     * @throws ReflectionException
     */
    public function useDemo(): bool
    {
        return $this->start(NasaKey::DEFAULT);
    }

    /**
     * @throws ReflectionException
     */
    protected function start(string $key): bool
    {
        NasaKey::save($key);

        DispatchController::deploy(StartPage::resolve(), GlobalVariables::make());

        return true;
    }
}
