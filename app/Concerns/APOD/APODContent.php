<?php

namespace App\Concerns\APOD;

use App\NutsAndBolts\PictureMenu;
use App\NutsAndBolts\PictureViewer;
use DateInvalidTimeZoneException;
use DateMalformedStringException;
use ReflectionException;
use Surface\Contracts\Windows\Primitives\Align;
use Surface\Contracts\Windows\Primitives\TKButton;
use Surface\Contracts\Windows\Primitives\TKCheckbox;
use Surface\Contracts\Windows\Primitives\TKColumn;
use Surface\Contracts\Windows\Primitives\TKDatepicker;
use Surface\Contracts\Windows\Primitives\TKGrid;
use Surface\Contracts\Windows\Primitives\TKLabel;
use Surface\Contracts\Windows\Primitives\TKPrimitiveGroup;
use Surface\Contracts\Windows\Primitives\TKRow;
use Surface\Contracts\Windows\Primitives\TKScrollView;
use Surface\Contracts\Windows\Primitives\TKSlider;
use Surface\Contracts\Windows\Primitives\TKVideo;
use Surface\Contracts\Windows\Styling\FontSpec;
use Surface\Contracts\Windows\Styling\FontWeight;
use Surface\Contracts\Windows\WindowException;
use Surface\NutsAndBolts\Color;

trait APODContent
{
    protected ?TKLabel $link = null;

    protected ?TKColumn $notice = null;

    protected ?TKScrollView $media_view = null;

    protected ?TKLabel $notice_text = null;

    protected ?TKButton $previous = null;

    protected ?TKLabel $meta = null;

    protected ?TKButton $load = null;

    protected ?TKCheckbox $hd = null;

    protected ?TKVideo $video = null;

    protected ?PictureViewer $picture = null;

    protected ?TKColumn $single = null;

    protected ?TKDatepicker $date = null;

    protected ?TKLabel $title_label = null;

    protected ?TKLabel $explanation = null;

    protected ?TKColumn $gallery = null;

    protected ?TKSlider $count = null;

    protected ?TKLabel $count_label = null;

    protected ?TKButton $gallery_load = null;

    protected ?TKGrid $cells = null;

    /** Right-click actions for the day's picture. */
    protected ?PictureMenu $single_menu = null;

    /** Right-click actions for the gallery's pictures. */
    protected ?PictureMenu $gallery_menu = null;

    public function date(): ?TKDatepicker
    {
        return $this->date;
    }

    public function hd(): ?TKCheckbox
    {
        return $this->hd;
    }

    public function video(): ?TKVideo
    {
        return $this->video;
    }

    /**
     * @throws DateMalformedStringException
     * @throws ReflectionException
     * @throws DateInvalidTimeZoneException
     */
    protected function setMainComponents(TKColumn $root): void
    {
        $form = $this->setForm($root);

        $this->date = $form->datepicker('date', today());
        $this->hd = $form->checkbox('hd', 'HD');
        $this->load = $form->button('load', 'Load');

        $this->single = $root->column('single', 8);
        $this->single->fill();
        $this->title_label = $this->single->label('title', '');
        $this->title_label->setFont(new FontSpec(22, FontWeight::BOLD));
        $this->meta = $this->single->label('meta', '');
        $this->meta->setTextColor(Color::hex('#8a8a8a'));
        $this->meta->setWrap(true);

        $this->setMultimedia();
        $this->single_menu = new PictureMenu('apod');

        $this->explanation = $this->single->label('explanation', '');
        $this->explanation->setWrap(true);

        $this->buildGallery($root);
    }

    protected function setBusy(bool $busy): void
    {
        $this->load->setEnabled(! $busy);
        $this->gallery_load->setEnabled(! $busy);
    }

    private function setMultimedia(): void
    {
        // What a failed load shows in place of the media. Outside the scroll view, which would
        // give the wrapping text an unbounded width.
        $this->notice = $this->single->column('notice', 8, 12);
        $this->notice_text = $this->notice->label('text', '');
        $this->notice_text->setWrap(true);
        $this->previous = $this->notice->button('previous', 'Load the day before');
        $this->previous->align(Align::START);
        $this->notice->hide();

        $media = $this->media_view = $this->single->scrollView('media');
        $media->fill()->minSize(0, 320);
        $stage = $media->column('stage', 6, 6);
        // Zoomable; zooming past the day's picture asks for its HD copy.
        $this->picture = new PictureViewer($stage, 'picture', fn (string $file) => $this->sharpenPicture($file));
        $this->video = $this->videoIn($stage, 'video');
        $this->video?->fill();
        $this->video?->hide();
        $this->link = $stage->label('link', '');
        $this->link->setWrap(true);
        $this->link->hide();
    }

    private function setForm(TKColumn $root): TKRow
    {
        $form = $root->row('form', 10);
        $form->label('date-label', 'Day');
        return $form;
    }

    private function buildGallery(TKColumn $root): void
    {
        $this->gallery = $root->column('gallery', 8);
        $this->gallery->fill();
        $controls = $this->gallery->row('controls', 10);
        $this->count = $controls->slider('count', 1, 12, 6);
        $this->count->minSize(220, 0);
        $this->count_label = $controls->label('count-label', '6 pictures');
        $this->gallery_load = $controls->button('load', 'Load gallery');
        $this->gallery_menu = new PictureMenu('apod');
        $scroll = $this->gallery->scrollView('cells');
        $scroll->fill();
        $this->cells = $scroll->grid('grid', 12, 6);
        $this->gallery->hide();
    }

    /**
     * A video view, or null where the toolkit has no media runtime (the caller shows a link).
     * @param TKPrimitiveGroup $in
     * @param string $name
     * @return TKVideo|null
     */
    private function videoIn(TKPrimitiveGroup $in, string $name): ?TKVideo
    {
        try {
            return $in->video($name);
        } catch (WindowException) {
            return null;
        }
    }
}
