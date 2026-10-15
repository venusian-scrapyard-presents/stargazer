<?php

namespace App\Services\Settings;

use App\Actions\Work\DispatchController;
use App\GlobalVariables;
use App\NutsAndBolts\NasaKey;
use App\NutsAndBolts\StartPage;
use ReflectionException;
use Surface\Contracts\Windows\Primitives\Align;
use Surface\Contracts\Windows\Primitives\TKButton;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKTextInput;
use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\Styling\FontWeight;
use Surface\Contracts\Windows\Styling\TextAlignment;

/**
 * The first-run form: asks for the api.nasa.gov key, stores it, then opens the start page.
 */
class KeyService
{
    /** api.nasa.gov's sign-up form; a key arrives by email. */
    public const string SIGNUP = 'https://api.nasa.gov/';

    protected ?TKTextInput $key = null;

    protected ?TKButton $save = null;

    public function mounted(TKColumn $root): void
    {
        $root->column('above')->fill(false, true);
        $panel = $root->column('panel', 12);
        $panel->align(Align::CENTER, Align::CENTER);
        $root->column('below')->fill(false, true);

        $panel->label('heading', 'NASA API key')
            ->setFont(new FontSpec(22, FontWeight::BOLD))
            ->setAlignment(TextAlignment::CENTER)
            ->align(Align::CENTER);
        $panel->label('about', 'Stargazer calls api.nasa.gov with this key. DEMO_KEY works, with low rate limits.')
            ->setWrap(true)
            ->setAlignment(TextAlignment::CENTER)
            ->minSize(460, 0);
        $panel->link('signup', 'Get a free key of your own at api.nasa.gov', self::SIGNUP)
            ->setAlignment(TextAlignment::CENTER)
            ->align(Align::CENTER);

        $form = $panel->row('form', 10);
        $form->align(Align::CENTER, Align::CENTER);
        $this->key = $form->textInput('key', NasaKey::DEFAULT);
        $this->key->minSize(360, 0);
        $this->save = $form->button('save', 'Save');
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
        NasaKey::save($key === '' ? NasaKey::DEFAULT : $key);

        DispatchController::deploy(StartPage::resolve(), GlobalVariables::make());

        return true;
    }
}
