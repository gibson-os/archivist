<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Test\Unit\Strategy;

use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Exception\ElementNotFoundException;
use Behat\Mink\Session;
use Codeception\Test\Unit;
use GibsonOS\Core\Service\CryptService;
use GibsonOS\Core\Service\DateTimeService;
use GibsonOS\Core\Service\FfmpegService;
use GibsonOS\Core\Service\ProcessService;
use GibsonOS\Core\Service\WebService;
use GibsonOS\Module\Archivist\Dto\Strategy;
use GibsonOS\Module\Archivist\Model\Rule;
use GibsonOS\Module\Archivist\Service\BrowserService;
use GibsonOS\Module\Archivist\Strategy\AudibleStrategy;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;

class AudibleStrategyTest extends Unit
{
    use ProphecyTrait;

    private AudibleStrategy $audibleStrategy;

    /**
     * @var ObjectProphecy|BrowserService
     */
    private $browserService;

    /**
     * @var ObjectProphecy|WebService
     */
    private $webService;

    /**
     * @var ObjectProphecy|FfmpegService
     */
    private $ffmpegService;

    /**
     * @var ObjectProphecy|ProcessService
     */
    private $processService;

    protected function _before(): void
    {
        $this->browserService = $this->prophesize(BrowserService::class);
        $this->webService = $this->prophesize(WebService::class);
        $this->ffmpegService = $this->prophesize(FfmpegService::class);
        $this->processService = $this->prophesize(ProcessService::class);
        $this->audibleStrategy = new AudibleStrategy(
            $this->browserService->reveal(),
            $this->webService->reveal(),
            $this->prophesize(LoggerInterface::class)->reveal(),
            $this->prophesize(CryptService::class)->reveal(),
            $this->prophesize(DateTimeService::class)->reveal(),
            $this->ffmpegService->reveal(),
            $this->processService->reveal()
        );
    }

    /**
     * @dataProvider getData
     */
    public function testGetSeriesFiles(string $name, string $content): void
    {
        /** @var ObjectProphecy|Session $session */
        $session = $this->prophesize(Session::class);
        /** @var ObjectProphecy|DocumentElement $page */
        $page = $this->prophesize(DocumentElement::class);
        $page->clickLink('Eine Seite vorwärts')
            ->shouldBeCalledOnce()
            ->will($this->throwException(new ElementNotFoundException($session->reveal())))
        ;
        $page->getContent()
            ->shouldBeCalledOnce()
            ->willReturn($content)
        ;
        $session->getPage()
            ->shouldBeCalledTimes(2)
            ->willReturn($page)
        ;
        $this->browserService->getSession()
            ->shouldBeCalledTimes(2)
            ->willReturn($session)
        ;

        foreach (
            $this->audibleStrategy->getFiles(
                (new Strategy('Audible', AudibleStrategy::class))->setConfigValue('elements', 'series'),
                new Rule()
            ) as $file
        ) {
            $this->assertSame($name, $file->getName());
        }
    }

    public function getData(): array
    {
        return [
            [
                '[#meinAudibleOriginal] Die 121ste Umdrehung um die Sonne',
                '<div id="adbl-library-content-row-B07TCMB54Y" class="adbl-library-content-row">
<div id="" class="bc-row-responsive
    bc-spacing-top-s2" style="">
<div class="bc-col-responsive
    bc-spacing-top-none 
    bc-col-2">
<div id="" class="bc-row-responsive" style="">  
                    <a class="bc-link
    bc-color-link" tabindex="0" href="/pd/Die-121ste-Umdrehung-um-die-Sonne-Hoerbuch/B07TCMB54Y?ref=a_library_t_c5_libItem_&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=VXS9CWK3P0QJB66DBPSN">
<img id="" class="bc-pub-block
    bc-image-inset-border js-only-element" src="https://m.media-amazon.com/images/I/51kWzW0e3uL._SL500_.jpg" alt="Die 121ste Umdrehung um die Sonne Titelbild">
                    </a>
</div>
</div>
<div class="bc-col-responsive
    bc-spacing-top-none 
    bc-col-10">
<div id="" class="bc-row-responsive
    display-flex" style="">
<div class="bc-col-responsive
    bc-col-9">
<span>
 	<ul class="bc-list
    bc-list-nostyle">
<li class="bc-list-item
    bc-spacing-s0_5">
                                <a class="bc-link
    bc-color-base" tabindex="0" href="/pd/Die-121ste-Umdrehung-um-die-Sonne-Hoerbuch/B07TCMB54Y?ref=a_library_t_c5_libItem_&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=VXS9CWK3P0QJB66DBPSN">
<span class="bc-text
    bc-size-headline3">Die 121ste Umdrehung um die Sonne: #meinAudibleOriginal - Sci-Fi</span>
                                </a>
</li>
<li class="bc-list-item">
</li>
<li class="bc-list-item
	authorLabel 
    bc-spacing-s0_5 
    bc-spacing-top-s0_5">
<span class="bc-text
    bc-color-secondary">
                                Geschrieben von:
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchAuthor=Philipp+Zimmermann&amp;ref=a_library_t_c5_libItem_author_1&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=VXS9CWK3P0QJB66DBPSN">
<span class="bc-text
    bc-size-callout">Philipp Zimmermann</span>
                                        </a>
                            </span>
</li>
<li class="bc-list-item
	narratorLabel 
    bc-spacing-s0_5">
<span class="bc-text
    bc-color-secondary">
                                Gesprochen von:
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=David+Nathan&amp;ref=a_library_t_c5_libItem_narrator_1&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=VXS9CWK3P0QJB66DBPSN">
<span class="bc-text
    bc-size-callout">David Nathan</span>
                                        </a>
                            </span>
</li>
<li class="bc-list-item
	seriesLabel 
    bc-spacing-s0_5">
<span class="bc-text
    bc-color-secondary">
                                Serie:
                                    <a class="bc-link
    bc-size-callout 
    bc-color-base" tabindex="0" href="/series/meinAudibleOriginal-Hoerbuecher/B07T9NJ7DZ?ref=a_library_t_c5_libItem_series_1&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=VXS9CWK3P0QJB66DBPSN">#meinAudibleOriginal</a>
                            </span>
</li>
<li class="bc-list-item
	rateAndReviewLabel 
    bc-spacing-s0_5">
<div class="bc-box
			bc-box-padding-none
			adbl-prod-rate-review-bar-group
    bc-spacing-top-none">
<input type="hidden" name="asinForRatingStars" value="B07TCMB54Y" class="asin-for-rating-stars">
<input type="hidden" name="ratingsCsrfToken" value="gieiKIlJ7pwJqcbCyEWUclfOt/p9cP9JHkSR5moAAAABAAAAAGCIXDpyYXcAAAAAFVfwRGgNifE9xfqJS///" class="ratings-csrf-token">
            <div class="bc-trigger
bc-trigger-tooltip">
  <div class="bc-rating-stars adbl-prod-rate-review-bar adbl-prod-rate-review-bar-overall" data-star-count="0" role="radiogroup" tabindex="0" asin="B07TCMB54Y" aria-describedby="tooltip1619549262060">
      <h3 class="bc-pub-offscreen">interactive rating stars</h3>
      <span class="bc-rating-star" data-index="1" data-text="1 Stern" role="radio" aria-checked="false" aria-label="1 Stern" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-tertiary bc-icon-star-empty-s2 bc-icon-fill-tertiary">
</i>
      </span>
      <span class="bc-rating-star" data-index="2" data-text="2 Sterne" role="radio" aria-checked="false" aria-label="2 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-tertiary bc-icon-star-empty-s2 bc-icon-fill-tertiary">
</i>
      </span>
      <span class="bc-rating-star" data-index="3" data-text="3 Sterne" role="radio" aria-checked="false" aria-label="3 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-tertiary bc-icon-star-empty-s2 bc-icon-fill-tertiary">
</i>
      </span>
      <span class="bc-rating-star" data-index="4" data-text="4 Sterne" role="radio" aria-checked="false" aria-label="4 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-tertiary bc-icon-star-empty-s2 bc-icon-fill-tertiary">
</i>
      </span>
      <span class="bc-rating-star" data-index="5" data-text="5 Sterne" role="radio" aria-checked="false" aria-label="5 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-tertiary bc-icon-star-empty-s2 bc-icon-fill-tertiary">
</i>
      </span>
  </div>
  <div class="bc-tooltip bc-tooltip-left bc-palette-inverse bc-rating-stars-tooltip bc-hidden" role="tooltip" id="tooltip1619549262060" style="top: -7px;">
      <div class="bc-tooltip-inner bc-color-background-inverse bc-color-background-base">
          <div class="bc-tooltip-message bc-color-base">1 Stern</div>
      </div>
  </div>
  </div>
    &nbsp;
                <a class="bc-link
    bc-color-base" tabindex="0" href="/write-review?rdpath=%2Fpd%2FB07TCMB54Y&amp;asin=B07TCMB54Y&amp;source=lib&amp;page=1&amp;ref=a_library_t_c5_review&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=VXS9CWK3P0QJB66DBPSN">Rezension schreiben</a>
</div>
</li>
<li class="bc-list-item
	summaryLabel 
    bc-spacing-s0_5 
    bc-spacing-top-s1">
<span class="bc-text
    merchandisingSummary 
    bc-size-body 
    bc-color-secondary"><p>Es sind die Rituale, die bis zum Schluss bleiben: Die Pille mit dem morgendlichen Kaffee, die Compact Disc mit den Liedern von früher, die...</p></span>
</li>
<li class="bc-list-item">
<input type="hidden" name="isFinished" value="false">
<div class="bc-box
			bc-box-padding-none
			adbl-library-item-button-row
    bc-spacing-top-s1" data-asin="B07TCMB54Y" data-collection-id="">
    <span id="add-to-favorites-button-B07TCMB54Y" class="bc-button
  bc-button-simple
  add-to-favorites-button  
  bc-button-small 
  bc-button-inline">
      <button class="bc-button-text" type="button" tabindex="0">
        <span class="bc-text
    bc-button-text-inner 
    bc-size-action-small">
                <span class="favorite-icon library-button-row-icon">&nbsp;</span>
<span class="bc-text">Zu Favoriten hinzufügen</span>
  </span>
      </button>
    </span>
    <span id="remove-from-favorites-button-B07TCMB54Y" class="bc-button
  bc-button-simple
  remove-from-favorites-button bc-pub-hidden 
  bc-button-small 
  bc-button-inline">
      <button class="bc-button-text" type="button" tabindex="0">
        <span class="bc-text
    bc-button-text-inner 
    bc-size-action-small">
                <span class="unfavorite-icon library-button-row-icon">&nbsp;</span>
<span class="bc-text">Aus Favoriten entfernen</span>
  </span>
      </button>
    </span>
    <span class="bc-button
  bc-button-simple
  add-item-to-collection-button 
  bc-button-small 
  bc-button-inline">
      <button class="bc-button-text" type="button" tabindex="0">
        <span class="bc-text
    bc-button-text-inner 
    bc-size-action-small">
                <span class="add-to-collection-icon library-button-row-icon">&nbsp;</span>
<span class="bc-text">Hinzufügen zu ...</span>
  </span>
      </button>
    </span>
<input type="hidden" name="collectionIds-B07TCMB54Y" value="" id="collectionIds-B07TCMB54Y">
            <!-- These input fields are needed to localize success and failure toasts for adding to and removing from favorites-->
<input type="hidden" name="addToFavoritesSuccessMessage" value="Erfolgreich zu Favoriten hinzugefügt">
<input type="hidden" name="addToFavoritesFailureMessage" value="Fehler beim Hinzufügen zu Favoriten">
<input type="hidden" name="removeFromFavoritesSuccessMessage" value="Erfolgreich von Favoritenentfernt">
<input type="hidden" name="removeFromFavoritesFailureMessage" value="Fehler beim Entfernen von Favoriten">
            <!-- These input fields are needed to localize success and failure toasts for removing from a collection-->
<input type="hidden" name="removeFromCollectionSuccessMessage" value="Erfolgreich von Sammlungentfernt">
<input type="hidden" name="removeFromCollectionFailureMessage" value="Fehler beim Entfernen von Sammlung">
    <span id="mark-as-unfinished-button-B07TCMB54Y" class="bc-button
  bc-button-simple
  mark-as-unfinished-button bc-pub-hidden 
  bc-button-small 
  bc-button-inline">
      <button class="bc-button-text" type="button" tabindex="0">
        <span class="bc-text
    bc-button-text-inner 
    bc-size-action-small">
            <span class="unfinished-icon library-button-row-icon">&nbsp;</span>
<span class="bc-text
    small-padding-left">Als nicht abgeschlossen markieren</span>
  </span>
      </button>
    </span>
    <span id="mark-as-finished-button-B07TCMB54Y" class="bc-button
  bc-button-simple
  mark-as-finished-button  
  bc-button-small 
  bc-button-inline">
      <button class="bc-button-text" type="button" tabindex="0">
        <span class="bc-text
    bc-button-text-inner 
    bc-size-action-small">
            <span class="finished-icon library-button-row-icon">&nbsp;</span>
<span class="bc-text
    small-padding-left">Als beendet markieren</span>
  </span>
      </button>
    </span>
</div>
</li>
                </ul>
</span>
</div>
<div class="bc-col-responsive
    adbl-library-select-row-checkbox bc-pub-hidden 
    bc-col-1 
    bc-col-offset-2">
<div style="height: 171px;" class="bc-box
			bc-box-padding-base
			checkbox-container">
<div id="" class="bc-checkbox
    adbl-library-item-select-checkbox adbl-library-item-select-checkbox-B07TCMB54Y">
	<label class="bc-label-wrapper ">
    <div class="bc-checkbox-wrapper">
        <input type="checkbox" autocomplete="off" data-asin="B07TCMB54Y" data-img-url="https://m.media-amazon.com/images/I/51kWzW0e3uL._SL500_.jpg" data-img-alt-text="Die 121ste Umdrehung um die Sonne Titelbild">
            <span class="bc-checkbox-icon-container bc-color-border-base">
            <span class="bc-checkbox-icon-inner">
                <div class="bc-color-background-active bc-checkbox-check">
<i aria-hidden="true" class="bc-icon
	bc-icon-fill-inverse
	bc-icon-check-s2
	bc-checkbox-icon 
	bc-icon-check 
	bc-color-inverse">
</i>
                </div>
            </span>
        </span>
    </div>
    <span class="bc-checkbox-label bc-size-callout">
    </span>
	</label>
</div>
</div>
</div>
<div class="bc-col-responsive
    adbl-library-action 
    bc-col-2 
    bc-col-offset-1">
<div id="" class="bc-row-responsive
    bc-spacing-top-s2" style="">
<span class="bc-text
    bc-pub-hidden 
    bc-color-secondary" id="time-remaining-finished-B07TCMB54Y">
    Beendet
</span>
<div id="time-remaining-display-B07TCMB54Y" class="bc-row-responsive" style="">
<div class="bc-col-responsive">
<span class="bc-text
    bc-color-secondary">40 Min.</span>
</div>
</div>
</div>
<div id="" class="bc-row-responsive
    adbl-library-item 
    bc-spacing-top-s2" style="">
        <input type="hidden" name="asin" value="B07TCMB54Y">
        <input type="hidden" name="contentType" value="Product">
        <input type="hidden" name="contentDeliveryType" value="SinglePartBook">
        <input type="hidden" name="asinIndex" value="1">
    <span class="bc-button
  bc-button-primary
  adbl-library-listen-now-button 
  bc-button-small">
      <button class="bc-button-text" type="button" tabindex="0">
        <span class="bc-text
    bc-button-text-inner 
    bc-size-action-small">
<span class="bc-text
    bc-size-callout">Jetzt anhören</span>
  </span>
      </button>
    </span>
</div>
<div id="" class="bc-row-responsive
    bc-spacing-top-s2" style="">
    <span class="bc-button
  bc-button-secondary
  adbl-lib-action-download 
  bc-button-small">
      <a class="bc-button-text" href="https://cds.audible.de/download?asin=B07TCMB54Y&amp;cust_id=J3k_6owI0CGG0qUojvqrTLH9k9gGJ3GpF2TsslShD4w8F5cFNZhlXLCoVg45&amp;codec=LC_128_44100_Stereo&amp;source=Audible&amp;type=AUDI" tabindex="0">
        <span class="bc-text
    bc-button-text-inner 
    bc-size-action-small">
<span class="bc-text
    bc-size-callout">Herunterladen</span>
  </span>
      </a>
    </span>
</div>
<div id="" class="bc-row-responsive
    bc-spacing-top-s2" style="">
<span class="bc-text
    bc-size-caption1 
    bc-color-secondary">
                Sie haben diesen Titel heruntergeladen
            </span>
</div>
</div>
</div>
</div>
</div>
<div id="" class="bc-row-responsive
    bc-spacing-top-base 
    bc-spacing-base" style="">
<div class="bc-col-responsive
    bc-col-10 
    bc-col-offset-2">
<div id="" class="bc-row-responsive
    library-item-divider" style="">
<div class="bc-divider
    bc-divider-secondary">
</div>
</div>
</div>
</div>
</div>',
            ],
        ];
    }
}
