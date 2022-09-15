<?php
declare(strict_types=1);

namespace GibsonOS\Module\Archivist\Test\Unit\Strategy;

use Behat\Mink\Element\DocumentElement;
use Behat\Mink\Element\NodeElement;
use Behat\Mink\Session;
use DMore\ChromeDriver\ChromeDriver;
use GibsonOS\Core\Service\CryptService;
use GibsonOS\Core\Service\DateTimeService;
use GibsonOS\Core\Service\FfmpegService;
use GibsonOS\Core\Service\ProcessService;
use GibsonOS\Core\Service\WebService;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Module\Archivist\Service\BrowserService;
use GibsonOS\Module\Archivist\Strategy\AudibleStrategy;
use GibsonOS\UnitTest\AbstractTest;
use phpmock\phpunit\PHPMock;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;

class AudibleStrategyTest extends AbstractTest
{
    use ProphecyTrait;
    use PHPMock;

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

    /**
     * @var ObjectProphecy|CryptService
     */
    private $cryptService;

    protected function _before(): void
    {
        putenv('TIMEZONE=Europe/Berlin');
        putenv('DATE_LATITUDE=51.2642156');
        putenv('DATE_LONGITUDE=6.8001438');
        $this->browserService = $this->prophesize(BrowserService::class);
        $this->webService = $this->prophesize(WebService::class);
        $this->ffmpegService = $this->prophesize(FfmpegService::class);
        $this->processService = $this->prophesize(ProcessService::class);
        $this->cryptService = $this->prophesize(CryptService::class);
        $this->audibleStrategy = new AudibleStrategy(
            $this->browserService->reveal(),
            $this->webService->reveal(),
            $this->prophesize(LoggerInterface::class)->reveal(),
            $this->cryptService->reveal(),
            $this->serviceManager->get(DateTimeService::class),
            $this->modelManager->reveal(),
            $this->ffmpegService->reveal(),
            $this->processService->reveal()
        );
    }

    public function testLogin(): void
    {
        $session = new Session(new ChromeDriver('http://localhost:9222', null, 'chrome://new-tab-page'));
        /** @var ObjectProphecy|DocumentElement $page */
        $page = $this->prophesize(DocumentElement::class);

        $this->browserService->getSession()
            ->shouldBeCalledOnce()
            ->willReturn($session)
        ;
        $this->browserService->getPage($session)
            ->shouldBeCalledOnce()
            ->willReturn($page->reveal())
        ;
        $this->browserService->loadPage($session, 'https://audible.de/')
            ->shouldBeCalledOnce()
            ->willReturn($page->reveal())
        ;
        $page->clickLink('Anmelden')
            ->shouldBeCalledOnce()
        ;
        $this->browserService->waitForElementById($session, 'ap_email')
            ->shouldBeCalledOnce()
            ->willReturn(new NodeElement('foo', $session))
        ;
        $this->cryptService->encrypt('Arthur')
            ->shouldBeCalledOnce()
            ->willReturn('Arthur')
        ;
        $this->cryptService->encrypt('Dent')
            ->shouldBeCalledOnce()
            ->willReturn('Dent')
        ;
        $this->cryptService->decrypt('Arthur')
            ->shouldBeCalledOnce()
            ->willReturn('Arthur')
        ;
        $this->cryptService->decrypt('Dent')
            ->shouldBeCalledOnce()
            ->willReturn('Dent')
        ;
        $this->browserService->fillFormFields($session, [
            'email' => 'Arthur',
            'password' => 'Dent',
        ])
            ->shouldBeCalledOnce()
        ;
        $page->pressButton('signInSubmit')
            ->shouldBeCalledOnce()
        ;
        $this->browserService->waitForLink($session, 'Bibliothek', 30000000)
            ->shouldBeCalledOnce()
            ->willReturn(new NodeElement('foo', $session))
        ;
        $page->clickLink('Bibliothek')
            ->shouldBeCalledOnce()
        ;
        $this->browserService->waitForElementById($session, 'lib-subheader-actions')
            ->shouldBeCalledOnce()
            ->willReturn(new NodeElement('foo', $session))
        ;

        $account = new Account();
        $this->audibleStrategy->setAccountParameters($account, [
            'email' => 'Arthur',
            'password' => 'Dent',
        ]);
        $this->audibleStrategy->setExecuteParameters($account, []);
    }

    public function testLoginWithCaptcha(): void
    {
    }

    /**
     * @dataProvider getData
     */
    public function testGetFiles(string $name, string $content, ?string $subContent): void
    {
        /** @var ObjectProphecy|Session $session */
        $session = $this->prophesize(Session::class);
        /** @var ObjectProphecy|DocumentElement $page */
        $page = $this->prophesize(DocumentElement::class);
        $account = (new Account())->setStrategy(AudibleStrategy::class);

        if ($subContent === null) {
            $page->getContent()->willReturn($content);
        } else {
            $page->getContent()->willReturn($content, $subContent, $content, $content);
            $this->browserService->waitForElementById($session->reveal(), 'lib-subheader-actions')
                ->shouldBeCalledOnce()
                ->willReturn($this->prophesize(NodeElement::class)->reveal())
            ;
            $session->getCurrentUrl()->shouldBeCalledOnce();
            $this->browserService->goto($session->reveal(), Argument::any())->shouldBeCalledOnce();
            $this->modelManager->saveWithoutChildren(Argument::any())->shouldBeCalledOnce();
        }

        $page->getContent()->shouldBeCalledTimes($subContent === null ? 1 : 2);
        $session->getPage()
            ->shouldBeCalledTimes($subContent === null ? 2 : 4)
            ->willReturn($page)
        ;
        $this->browserService->getSession()
            ->shouldBeCalledTimes($subContent === null ? 2 : 4)
            ->willReturn($session)
        ;

        $this->assertSame($name, $this->audibleStrategy->getFiles($account)->current()->getName());
    }

    public function getData(): array
    {
        return [
            '[#meinAudibleOriginal] Die 121ste Umdrehung um die Sonne' => [
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
                null,
            ],
            '[Die phantastischen Fälle des Rufus T. Feuerflieg] 6 Zurück in die Gegenwart' => [
                '[Die phantastischen Fälle des Rufus T. Feuerflieg] 6 Zurück in die Gegenwart',
                '<div id="adbl-library-content-row-B08ZJL9F8L" class="adbl-library-content-row">
<div id="" class="bc-row-responsive
    bc-spacing-top-s2" style="">
<div class="bc-col-responsive
    bc-spacing-top-none 
    bc-col-2">
<div id="" class="bc-row-responsive" style="">
                    <a class="bc-link
    bc-color-link" tabindex="0" href="/pd/Zurueck-in-die-Gegenwart-Die-phantastischen-Faelle-des-Rufus-T-Feuerflieg-6-Hoerbuch/B08ZJL9F8L?ref=a_library_t_c5_libItem_&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=QASEDZM5VX11R8DMRXFF">
<img id="" class="bc-pub-block
    bc-image-inset-border js-only-element" src="https://m.media-amazon.com/images/I/51ZKhfcqx8L._SL500_.jpg" alt="Zurück in die Gegenwart. Die phantastischen Fälle des Rufus T. Feuerflieg 6 Titelbild">
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
    bc-color-base" tabindex="0" href="/pd/Zurueck-in-die-Gegenwart-Die-phantastischen-Faelle-des-Rufus-T-Feuerflieg-6-Hoerbuch/B08ZJL9F8L?ref=a_library_t_c5_libItem_&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=QASEDZM5VX11R8DMRXFF">
<span class="bc-text
    bc-size-headline3">Zurück in die Gegenwart. Die phantastischen Fälle des Rufus T. Feuerflieg 6: Ghostsitter Stories</span>
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
    bc-color-base" tabindex="0" href="/author/Tommy-Krappweis/B00458Q29G?ref=a_library_t_c5_libItem_author_14&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=QASEDZM5VX11R8DMRXFF">
<span class="bc-text
    bc-size-callout">Tommy Krappweis</span>
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
    bc-color-base" tabindex="0" href="/search?searchNarrator=Kai+Taschner&amp;ref=a_library_t_c5_libItem_narrator_14&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=QASEDZM5VX11R8DMRXFF">
<span class="bc-text
    bc-size-callout">Kai Taschner</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=Wigald+Boning&amp;ref=a_library_t_c5_libItem_narrator_14&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=QASEDZM5VX11R8DMRXFF">
<span class="bc-text
    bc-size-callout">Wigald Boning</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=Teresa+Boning&amp;ref=a_library_t_c5_libItem_narrator_14&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=QASEDZM5VX11R8DMRXFF">
<span class="bc-text
    bc-size-callout">Teresa Boning</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=David+Gromer&amp;ref=a_library_t_c5_libItem_narrator_14&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=QASEDZM5VX11R8DMRXFF">
<span class="bc-text
    bc-size-callout">David Gromer</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=Tommy+Krappweis&amp;ref=a_library_t_c5_libItem_narrator_14&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=QASEDZM5VX11R8DMRXFF">
<span class="bc-text
    bc-size-callout">Tommy Krappweis</span>
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
    bc-color-base" tabindex="0" href="/series/Die-phantastischen-Faelle-des-Rufus-T-Feuerflieg-Hoerbuecher/B086X4946J?ref=a_library_t_c5_libItem_series_1&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=QASEDZM5VX11R8DMRXFF">Die phantastischen Fälle des Rufus T. Feuerflieg</a>, Titel 6
                            </span>
</li>
<li class="bc-list-item
	rateAndReviewLabel 
    bc-spacing-s0_5">
<div class="bc-box
			bc-box-padding-none
			adbl-prod-rate-review-bar-group
    bc-spacing-top-none">
<input type="hidden" name="asinForRatingStars" value="B08ZJL9F8L" class="asin-for-rating-stars">
<input type="hidden" name="ratingsCsrfToken" value="guAxd/fuub5ss+SZL2OwE71cQCQFrr7HzE40vyMAAAABAAAAAGCIcWRyYXcAAAAAFVfwRGgNifE9xfqJS///" class="ratings-csrf-token">
            <div class="bc-trigger
bc-trigger-tooltip">
  <div class="bc-rating-stars adbl-prod-rate-review-bar adbl-prod-rate-review-bar-overall" data-star-count="5" role="radiogroup" tabindex="0" asin="B08ZJL9F8L">
      <h3 class="bc-pub-offscreen">interactive rating stars</h3>
      <span class="bc-rating-star" data-index="1" data-text="1 Stern" role="radio" aria-checked="false" aria-label="1 Stern" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-star-fill-s2 bc-icon-fill-progress">
</i>
      </span>
      <span class="bc-rating-star" data-index="2" data-text="2 Sterne" role="radio" aria-checked="false" aria-label="2 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-star-fill-s2 bc-icon-fill-progress">
</i>
      </span>
      <span class="bc-rating-star" data-index="3" data-text="3 Sterne" role="radio" aria-checked="false" aria-label="3 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-star-fill-s2 bc-icon-fill-progress">
</i>
      </span>
      <span class="bc-rating-star" data-index="4" data-text="4 Sterne" role="radio" aria-checked="false" aria-label="4 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-star-fill-s2 bc-icon-fill-progress">
</i>
      </span>
      <span class="bc-rating-star" data-index="5" data-text="5 Sterne" role="radio" aria-checked="true" aria-label="5 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-star-fill-s2 bc-icon-fill-progress">
</i>
      </span>
  </div>
  <div class="bc-tooltip bc-tooltip-left bc-hidden  bc-palette-inverse bc-rating-stars-tooltip" role="tooltip">
      <div class="bc-tooltip-inner bc-color-background-inverse bc-color-background-base">
          <div class="bc-tooltip-message bc-color-base">
          </div>
      </div>
  </div>
  </div>
    &nbsp;
                <a class="bc-link
    bc-color-base" tabindex="0" href="/write-review?rdpath=%2Fpd%2FB08ZJL9F8L&amp;asin=B08ZJL9F8L&amp;source=lib&amp;page=1&amp;ref=a_library_t_c5_review&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=QASEDZM5VX11R8DMRXFF">Rezension schreiben</a>
</div>
</li>
<li class="bc-list-item
	summaryLabel 
    bc-spacing-s0_5 
    bc-spacing-top-s1">
<span class="bc-text
    merchandisingSummary 
    bc-size-body 
    bc-color-secondary"><p>Rufus T. Feuerflieg ist es nicht gewöhnt, seine Zunge im Zaum halten zu müssen. Doch diesmal muss er lernen, nicht alles auszusprechen...</p></span>
</li>
<li class="bc-list-item">
<input type="hidden" name="isFinished" value="true">
<div class="bc-box
			bc-box-padding-none
			adbl-library-item-button-row
    bc-spacing-top-s1" data-asin="B08ZJL9F8L" data-collection-id="">
    <span id="add-to-favorites-button-B08ZJL9F8L" class="bc-button
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
    <span id="remove-from-favorites-button-B08ZJL9F8L" class="bc-button
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
<input type="hidden" name="collectionIds-B08ZJL9F8L" value="[04532835-497f-47ff-8b1c-ad2a67acc1ab]" id="collectionIds-B08ZJL9F8L">
            <!-- These input fields are needed to localize success and failure toasts for adding to and removing from favorites-->
<input type="hidden" name="addToFavoritesSuccessMessage" value="Erfolgreich zu Favoriten hinzugefügt">
<input type="hidden" name="addToFavoritesFailureMessage" value="Fehler beim Hinzufügen zu Favoriten">
<input type="hidden" name="removeFromFavoritesSuccessMessage" value="Erfolgreich von Favoritenentfernt">
<input type="hidden" name="removeFromFavoritesFailureMessage" value="Fehler beim Entfernen von Favoriten">
            <!-- These input fields are needed to localize success and failure toasts for removing from a collection-->
<input type="hidden" name="removeFromCollectionSuccessMessage" value="Erfolgreich von Sammlungentfernt">
<input type="hidden" name="removeFromCollectionFailureMessage" value="Fehler beim Entfernen von Sammlung">
    <span id="mark-as-unfinished-button-B08ZJL9F8L" class="bc-button
  bc-button-simple
  mark-as-unfinished-button  
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
    <span id="mark-as-finished-button-B08ZJL9F8L" class="bc-button
  bc-button-simple
  mark-as-finished-button bc-pub-hidden 
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
    adbl-library-item-select-checkbox adbl-library-item-select-checkbox-B08ZJL9F8L">
	<label class="bc-label-wrapper ">
    <div class="bc-checkbox-wrapper">
        <input type="checkbox" autocomplete="off" data-asin="B08ZJL9F8L" data-img-url="https://m.media-amazon.com/images/I/51ZKhfcqx8L._SL500_.jpg" data-img-alt-text="Zurück in die Gegenwart. Die phantastischen Fälle des Rufus T. Feuerflieg 6 Titelbild">
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
    bc-color-secondary" id="time-remaining-finished-B08ZJL9F8L">
    Beendet
</span>
</div>
<div id="" class="bc-row-responsive
    adbl-library-item 
    bc-spacing-top-s2" style="">
        <input type="hidden" name="asin" value="B08ZJL9F8L">
        <input type="hidden" name="contentType" value="Performance">
        <input type="hidden" name="contentDeliveryType" value="SinglePartBook">
        <input type="hidden" name="asinIndex" value="14">
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
      <a class="bc-button-text" href="https://cds.audible.de/download?asin=B08ZJL9F8L&amp;cust_id=RbEr0w-I2JY7K5-f-Wp71DEWNIu_Hrsa9BJcIIcDm2nBy1112kCzisBL9YZ4&amp;codec=LC_128_44100_Stereo&amp;source=Audible&amp;type=AUDI" tabindex="0">
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
                null,
            ],
            '[Bibi und Tina 1-50] 24 Der Millionär' => [
                '[Bibi und Tina 1-50] 24 Der Millionär',
                '<div id="adbl-library-content-row-B004JVIMPG" class="adbl-library-content-row">
<div id="" class="bc-row-responsive
    bc-spacing-top-s2" style="">
<div class="bc-col-responsive
    bc-spacing-top-none 
    bc-col-2">
<div id="" class="bc-row-responsive" style="">
                    <a class="bc-link
    bc-color-link" tabindex="0" href="/pd/Der-Millionaer-Hoerbuch/B004JVIMPG?ref=a_library_t_c5_libItem_&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=QASEDZM5VX11R8DMRXFF">
<img id="" class="bc-pub-block
    bc-image-inset-border js-only-element" src="https://m.media-amazon.com/images/I/61gDFcr2YaL._SL500_.jpg" alt="Der Millionär Titelbild">
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
    bc-color-base" tabindex="0" href="/pd/Der-Millionaer-Hoerbuch/B004JVIMPG?ref=a_library_t_c5_libItem_&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=QASEDZM5VX11R8DMRXFF">
<span class="bc-text
    bc-size-headline3">Der Millionär: Bibi und Tina 24</span>
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
    bc-color-base" tabindex="0" href="/search?searchAuthor=Ulf+Tiehm&amp;ref=a_library_t_c5_libItem_author_18&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=QASEDZM5VX11R8DMRXFF">
<span class="bc-text
    bc-size-callout">Ulf Tiehm</span>
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
    bc-color-base" tabindex="0" href="/search?searchNarrator=Susanna+Bonas%C3%A9wicz&amp;ref=a_library_t_c5_libItem_narrator_18&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=QASEDZM5VX11R8DMRXFF">
<span class="bc-text
    bc-size-callout">Susanna Bonaséwicz</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=Dorette+Hugo&amp;ref=a_library_t_c5_libItem_narrator_18&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=QASEDZM5VX11R8DMRXFF">
<span class="bc-text
    bc-size-callout">Dorette Hugo</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=Joachim+Nottke&amp;ref=a_library_t_c5_libItem_narrator_18&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=QASEDZM5VX11R8DMRXFF">
<span class="bc-text
    bc-size-callout">Joachim Nottke</span>
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
    bc-color-base" tabindex="0" href="/series/Bibi-und-Tina-Hoerbuecher/B00KCOTDE8?ref=a_library_t_c5_libItem_series_1&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=QASEDZM5VX11R8DMRXFF">Bibi und Tina 1-50</a>, Titel 24
                            </span>
</li>
<li class="bc-list-item
	rateAndReviewLabel 
    bc-spacing-s0_5">
<div class="bc-box
			bc-box-padding-none
			adbl-prod-rate-review-bar-group
    bc-spacing-top-none">
<input type="hidden" name="asinForRatingStars" value="B004JVIMPG" class="asin-for-rating-stars">
<input type="hidden" name="ratingsCsrfToken" value="ghDRLClVvTkbTmHjuc+AZuLS25sHfq4f1SYKRBQAAAABAAAAAGCIcWRyYXcAAAAAFVfwRGgNifE9xfqJS///" class="ratings-csrf-token">
            <div class="bc-trigger
bc-trigger-tooltip">
  <div class="bc-rating-stars adbl-prod-rate-review-bar adbl-prod-rate-review-bar-overall" data-star-count="0" role="radiogroup" tabindex="0" asin="B004JVIMPG">
      <h3 class="bc-pub-offscreen">interactive rating stars</h3>
      <span class="bc-rating-star" data-index="1" data-text="1 Stern" role="radio" aria-checked="false" aria-label="1 Stern" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="2" data-text="2 Sterne" role="radio" aria-checked="false" aria-label="2 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="3" data-text="3 Sterne" role="radio" aria-checked="false" aria-label="3 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="4" data-text="4 Sterne" role="radio" aria-checked="false" aria-label="4 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="5" data-text="5 Sterne" role="radio" aria-checked="false" aria-label="5 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
  </div>
  <div class="bc-tooltip bc-tooltip-left bc-hidden  bc-palette-inverse bc-rating-stars-tooltip" role="tooltip">
      <div class="bc-tooltip-inner bc-color-background-inverse bc-color-background-base">
          <div class="bc-tooltip-message bc-color-base">
          </div>
      </div>
  </div>
  </div>
    &nbsp;
                <a class="bc-link
    bc-color-base" tabindex="0" href="/write-review?rdpath=%2Fpd%2FB004JVIMPG&amp;asin=B004JVIMPG&amp;source=lib&amp;page=1&amp;ref=a_library_t_c5_review&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=QASEDZM5VX11R8DMRXFF">Rezension schreiben</a>
</div>
</li>
<li class="bc-list-item
	summaryLabel 
    bc-spacing-s0_5 
    bc-spacing-top-s1">
<span class="bc-text
    merchandisingSummary 
    bc-size-body 
    bc-color-secondary">An der Alten Mühle soll gebaut werden, mitten in der Natur. Bibi und Tina reiten schnellstens zum Grafen von Falkenstein...</span>
</li>
<li class="bc-list-item">
<input type="hidden" name="isFinished" value="true">
<div class="bc-box
			bc-box-padding-none
			adbl-library-item-button-row
    bc-spacing-top-s1" data-asin="B004JVIMPG" data-collection-id="">
    <span id="add-to-favorites-button-B004JVIMPG" class="bc-button
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
    <span id="remove-from-favorites-button-B004JVIMPG" class="bc-button
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
<input type="hidden" name="collectionIds-B004JVIMPG" value="[4c2089b3-ec42-4dfd-a661-ec34029b6b29]" id="collectionIds-B004JVIMPG">
            <!-- These input fields are needed to localize success and failure toasts for adding to and removing from favorites-->
<input type="hidden" name="addToFavoritesSuccessMessage" value="Erfolgreich zu Favoriten hinzugefügt">
<input type="hidden" name="addToFavoritesFailureMessage" value="Fehler beim Hinzufügen zu Favoriten">
<input type="hidden" name="removeFromFavoritesSuccessMessage" value="Erfolgreich von Favoritenentfernt">
<input type="hidden" name="removeFromFavoritesFailureMessage" value="Fehler beim Entfernen von Favoriten">
            <!-- These input fields are needed to localize success and failure toasts for removing from a collection-->
<input type="hidden" name="removeFromCollectionSuccessMessage" value="Erfolgreich von Sammlungentfernt">
<input type="hidden" name="removeFromCollectionFailureMessage" value="Fehler beim Entfernen von Sammlung">
    <span id="mark-as-unfinished-button-B004JVIMPG" class="bc-button
  bc-button-simple
  mark-as-unfinished-button  
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
    <span id="mark-as-finished-button-B004JVIMPG" class="bc-button
  bc-button-simple
  mark-as-finished-button bc-pub-hidden 
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
    adbl-library-item-select-checkbox adbl-library-item-select-checkbox-B004JVIMPG">
	<label class="bc-label-wrapper ">
    <div class="bc-checkbox-wrapper">
        <input type="checkbox" autocomplete="off" data-asin="B004JVIMPG" data-img-url="https://m.media-amazon.com/images/I/61gDFcr2YaL._SL500_.jpg" data-img-alt-text="Der Millionär Titelbild">
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
    bc-color-secondary" id="time-remaining-finished-B004JVIMPG">
    Beendet
</span>
</div>
<div id="" class="bc-row-responsive
    adbl-library-item 
    bc-spacing-top-s2" style="">
        <input type="hidden" name="asin" value="B004JVIMPG">
        <input type="hidden" name="contentType" value="Performance">
        <input type="hidden" name="contentDeliveryType" value="SinglePartBook">
        <input type="hidden" name="asinIndex" value="18">
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
      <a class="bc-button-text" href="https://cds.audible.de/download?asin=B004JVIMPG&amp;cust_id=UyEjTJfJn1CWgioCMoy-GSqJ3a7cXDFDchIJYdEvp7ZSJYdM51c7r0jyJs-B&amp;codec=LC_128_44100_Stereo&amp;source=Audible&amp;type=AUDI" tabindex="0">
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
                null,
            ],
            '[Die Amazonas-Detektive] 1 Verschwörung im Dschungel' => [
                '[Die Amazonas-Detektive] 1 Verschwörung im Dschungel',
                '<div id="adbl-library-content-row-B08R7KHYVT" class="adbl-library-content-row">
<div id="" class="bc-row-responsive
    bc-spacing-top-s2" style="">
<div class="bc-col-responsive
    bc-spacing-top-none 
    bc-col-2">
<div id="" class="bc-row-responsive" style="">
                    <a class="bc-link
    bc-color-link" tabindex="0" href="/pd/Verschwoerung-im-Dschungel-Hoerbuch/B08R7KHYVT?ref=a_library_t_c5_libItem_&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">
<img id="" class="bc-pub-block
    bc-image-inset-border js-only-element" src="https://m.media-amazon.com/images/I/61y7cYW0WCL._SL500_.jpg" alt="Verschwörung im Dschungel Titelbild">
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
    bc-color-base" tabindex="0" href="/pd/Verschwoerung-im-Dschungel-Hoerbuch/B08R7KHYVT?ref=a_library_t_c5_libItem_&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">
<span class="bc-text
    bc-size-headline3">Verschwörung im Dschungel: Die Amazonas-Detektive 1</span>
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
    bc-color-base" tabindex="0" href="/author/Antonia-Michaelis/B00458I7Q2?ref=a_library_t_c5_libItem_author_0&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">
<span class="bc-text
    bc-size-callout">Antonia Michaelis</span>
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
    bc-color-base" tabindex="0" href="/search?searchNarrator=Tim+G%C3%B6ssler&amp;ref=a_library_t_c5_libItem_narrator_0&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">
<span class="bc-text
    bc-size-callout">Tim Gössler</span>
                                        </a>
                            </span>
</li>
<li class="bc-list-item
	rateAndReviewLabel 
    bc-spacing-s0_5">
<div class="bc-box
			bc-box-padding-none
			adbl-prod-rate-review-bar-group
    bc-spacing-top-none">
<input type="hidden" name="asinForRatingStars" value="B08R7KHYVT" class="asin-for-rating-stars">
<input type="hidden" name="ratingsCsrfToken" value="gsOVR6mPjA0FzcNP/cB3Gt0xeu1qpFHIO23yv3gAAAABAAAAAGCIcrtyYXcAAAAAFVfwRGgNifE9xfqJS///" class="ratings-csrf-token">
            <div class="bc-trigger
bc-trigger-tooltip">
  <div class="bc-rating-stars adbl-prod-rate-review-bar adbl-prod-rate-review-bar-overall" data-star-count="1" role="radiogroup" tabindex="0" asin="B08R7KHYVT">
      <h3 class="bc-pub-offscreen">interactive rating stars</h3>
      <span class="bc-rating-star" data-index="1" data-text="1 Stern" role="radio" aria-checked="true" aria-label="1 Stern" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-star-fill-s2 bc-icon-fill-progress">
</i>
      </span>
      <span class="bc-rating-star" data-index="2" data-text="2 Sterne" role="radio" aria-checked="false" aria-label="2 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="3" data-text="3 Sterne" role="radio" aria-checked="false" aria-label="3 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="4" data-text="4 Sterne" role="radio" aria-checked="false" aria-label="4 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="5" data-text="5 Sterne" role="radio" aria-checked="false" aria-label="5 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
  </div>
  <div class="bc-tooltip bc-tooltip-left bc-hidden  bc-palette-inverse bc-rating-stars-tooltip" role="tooltip">
      <div class="bc-tooltip-inner bc-color-background-inverse bc-color-background-base">
          <div class="bc-tooltip-message bc-color-base">
          </div>
      </div>
  </div>
  </div>
    &nbsp;
                <a class="bc-link
    bc-color-base" tabindex="0" href="/write-review?rdpath=%2Fpd%2FB08R7KHYVT&amp;asin=B08R7KHYVT&amp;source=lib&amp;page=2&amp;ref=a_library_t_c5_review&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">Rezension schreiben</a>
</div>
</li>
<li class="bc-list-item
	summaryLabel 
    bc-spacing-s0_5 
    bc-spacing-top-s1">
<span class="bc-text
    merchandisingSummary 
    bc-size-body 
    bc-color-secondary"><p>Der Straßenjunge Pablo lebt allein in einer alten Ruine in der Großstadt Manaus. Eines Tages verschwindet sein Freund, der Student Miguel...</p></span>
</li>
<li class="bc-list-item">
<input type="hidden" name="isFinished" value="true">
<div class="bc-box
			bc-box-padding-none
			adbl-library-item-button-row
    bc-spacing-top-s1" data-asin="B08R7KHYVT" data-collection-id="">
    <span id="add-to-favorites-button-B08R7KHYVT" class="bc-button
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
    <span id="remove-from-favorites-button-B08R7KHYVT" class="bc-button
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
<input type="hidden" name="collectionIds-B08R7KHYVT" value="" id="collectionIds-B08R7KHYVT">
            <!-- These input fields are needed to localize success and failure toasts for adding to and removing from favorites-->
<input type="hidden" name="addToFavoritesSuccessMessage" value="Erfolgreich zu Favoriten hinzugefügt">
<input type="hidden" name="addToFavoritesFailureMessage" value="Fehler beim Hinzufügen zu Favoriten">
<input type="hidden" name="removeFromFavoritesSuccessMessage" value="Erfolgreich von Favoritenentfernt">
<input type="hidden" name="removeFromFavoritesFailureMessage" value="Fehler beim Entfernen von Favoriten">
            <!-- These input fields are needed to localize success and failure toasts for removing from a collection-->
<input type="hidden" name="removeFromCollectionSuccessMessage" value="Erfolgreich von Sammlungentfernt">
<input type="hidden" name="removeFromCollectionFailureMessage" value="Fehler beim Entfernen von Sammlung">
    <span id="mark-as-unfinished-button-B08R7KHYVT" class="bc-button
  bc-button-simple
  mark-as-unfinished-button  
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
    <span id="mark-as-finished-button-B08R7KHYVT" class="bc-button
  bc-button-simple
  mark-as-finished-button bc-pub-hidden 
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
    adbl-library-item-select-checkbox adbl-library-item-select-checkbox-B08R7KHYVT">
	<label class="bc-label-wrapper ">
    <div class="bc-checkbox-wrapper">
        <input type="checkbox" autocomplete="off" data-asin="B08R7KHYVT" data-img-url="https://m.media-amazon.com/images/I/61y7cYW0WCL._SL500_.jpg" data-img-alt-text="Verschwörung im Dschungel Titelbild">
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
    bc-color-secondary" id="time-remaining-finished-B08R7KHYVT">
    Beendet
</span>
</div>
<div id="" class="bc-row-responsive
    adbl-library-item 
    bc-spacing-top-s2" style="">
        <input type="hidden" name="asin" value="B08R7KHYVT">
        <input type="hidden" name="contentType" value="Product">
        <input type="hidden" name="contentDeliveryType" value="SinglePartBook">
        <input type="hidden" name="asinIndex" value="0">
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
      <a class="bc-button-text" href="https://cds.audible.de/download?asin=B08R7KHYVT&amp;cust_id=KZ1GJb862rQdunI5CUN4pMyaDlgT1JTjZgLLUTP1PYW-PujAAwCA7TflLv-T&amp;codec=LC_128_44100_Stereo&amp;source=Audible&amp;type=AUDI" tabindex="0">
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
                null,
            ],
            '[Ghostsitter] 5 Die komplette Staffel' => [
                '[Ghostsitter] 5 Die komplette Staffel',
                '<div id="adbl-library-content-row-B081ZF4X1M" class="adbl-library-content-row">
<div id="" class="bc-row-responsive
    bc-spacing-top-s2" style="">
<div class="bc-col-responsive
    bc-spacing-top-none 
    bc-col-2">
<div id="" class="bc-row-responsive" style="">
                    <a class="bc-link
    bc-color-link" tabindex="0" href="/pd/Ghostsitter-Die-komplette-5-Staffel-Hoerbuch/B081ZF4X1M?ref=a_library_t_c5_libItem_&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">
<img id="" class="bc-pub-block
    bc-image-inset-border js-only-element" src="https://m.media-amazon.com/images/I/61mzizeM6jL._SL500_.jpg" alt="Ghostsitter: Die komplette 5. Staffel Titelbild">
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
    bc-color-base" tabindex="0" href="/pd/Ghostsitter-Die-komplette-5-Staffel-Hoerbuch/B081ZF4X1M?ref=a_library_t_c5_libItem_&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">
<span class="bc-text
    bc-size-headline3">Ghostsitter: Die komplette 5. Staffel</span>
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
    bc-color-base" tabindex="0" href="/search?searchAuthor=Tommy+Krappweis&amp;ref=a_library_t_c5_libItem_author_13&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">
<span class="bc-text
    bc-size-callout">Tommy Krappweis</span>
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
    bc-color-base" tabindex="0" href="/search?searchNarrator=Kai+Taschner&amp;ref=a_library_t_c5_libItem_narrator_13&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">
<span class="bc-text
    bc-size-callout">Kai Taschner</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=Felix+Str%C3%BCven&amp;ref=a_library_t_c5_libItem_narrator_13&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">
<span class="bc-text
    bc-size-callout">Felix Strüven</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=Christoph+Maria+Herbst&amp;ref=a_library_t_c5_libItem_narrator_13&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">
<span class="bc-text
    bc-size-callout">Christoph Maria Herbst</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=Paulina+R%C3%BCmmelein&amp;ref=a_library_t_c5_libItem_narrator_13&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">
<span class="bc-text
    bc-size-callout">Paulina Rümmelein</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=Arlett+Drexler&amp;ref=a_library_t_c5_libItem_narrator_13&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">
<span class="bc-text
    bc-size-callout">Arlett Drexler</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=Detlef+Tams&amp;ref=a_library_t_c5_libItem_narrator_13&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">
<span class="bc-text
    bc-size-callout">Detlef Tams</span>
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
    bc-color-base" tabindex="0" href="/series/Ghostsitter-Hoerbuecher/B07B7N6V4Z?ref=a_library_t_c5_libItem_series_1&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">Ghostsitter</a>, Titel 5
                            </span>
</li>
<li class="bc-list-item
	rateAndReviewLabel 
    bc-spacing-s0_5">
<div class="bc-box
			bc-box-padding-none
			adbl-prod-rate-review-bar-group
    bc-spacing-top-none">
<input type="hidden" name="asinForRatingStars" value="B081ZF4X1M" class="asin-for-rating-stars">
<input type="hidden" name="ratingsCsrfToken" value="ggAgTUQuAdjYSHTpHCtftRQhRPDiL4oP+Z96CDwAAAABAAAAAGCIcrtyYXcAAAAAFVfwRGgNifE9xfqJS///" class="ratings-csrf-token">
            <div class="bc-trigger
bc-trigger-tooltip">
  <div class="bc-rating-stars adbl-prod-rate-review-bar adbl-prod-rate-review-bar-overall" data-star-count="5" role="radiogroup" tabindex="0" asin="B081ZF4X1M">
      <h3 class="bc-pub-offscreen">interactive rating stars</h3>
      <span class="bc-rating-star" data-index="1" data-text="1 Stern" role="radio" aria-checked="false" aria-label="1 Stern" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-star-fill-s2 bc-icon-fill-progress">
</i>
      </span>
      <span class="bc-rating-star" data-index="2" data-text="2 Sterne" role="radio" aria-checked="false" aria-label="2 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-star-fill-s2 bc-icon-fill-progress">
</i>
      </span>
      <span class="bc-rating-star" data-index="3" data-text="3 Sterne" role="radio" aria-checked="false" aria-label="3 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-star-fill-s2 bc-icon-fill-progress">
</i>
      </span>
      <span class="bc-rating-star" data-index="4" data-text="4 Sterne" role="radio" aria-checked="false" aria-label="4 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-star-fill-s2 bc-icon-fill-progress">
</i>
      </span>
      <span class="bc-rating-star" data-index="5" data-text="5 Sterne" role="radio" aria-checked="true" aria-label="5 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-star-fill-s2 bc-icon-fill-progress">
</i>
      </span>
  </div>
  <div class="bc-tooltip bc-tooltip-left bc-hidden  bc-palette-inverse bc-rating-stars-tooltip" role="tooltip">
      <div class="bc-tooltip-inner bc-color-background-inverse bc-color-background-base">
          <div class="bc-tooltip-message bc-color-base">
          </div>
      </div>
  </div>
  </div>
    &nbsp;
                <a class="bc-link
    bc-color-base" tabindex="0" href="/write-review?rdpath=%2Fpd%2FB081ZF4X1M&amp;asin=B081ZF4X1M&amp;source=lib&amp;page=2&amp;ref=a_library_t_c5_review&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">Rezension schreiben</a>
</div>
</li>
<li class="bc-list-item
	summaryLabel 
    bc-spacing-s0_5 
    bc-spacing-top-s1">
<span class="bc-text
    merchandisingSummary 
    bc-size-body 
    bc-color-secondary"><p>Tom ist mal wieder baff: Der neueste Vampirroman von Starautorin Tiffany Schuster soll verfilmt werden und er spielt... in einer Geisterbahn...</p></span>
</li>
<li class="bc-list-item">
<input type="hidden" name="isFinished" value="true">
<div class="bc-box
			bc-box-padding-none
			adbl-library-item-button-row
    bc-spacing-top-s1" data-asin="B081ZF4X1M" data-collection-id="">
    <span id="add-to-favorites-button-B081ZF4X1M" class="bc-button
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
    <span id="remove-from-favorites-button-B081ZF4X1M" class="bc-button
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
<input type="hidden" name="collectionIds-B081ZF4X1M" value="[04532835-497f-47ff-8b1c-ad2a67acc1ab]" id="collectionIds-B081ZF4X1M">
            <!-- These input fields are needed to localize success and failure toasts for adding to and removing from favorites-->
<input type="hidden" name="addToFavoritesSuccessMessage" value="Erfolgreich zu Favoriten hinzugefügt">
<input type="hidden" name="addToFavoritesFailureMessage" value="Fehler beim Hinzufügen zu Favoriten">
<input type="hidden" name="removeFromFavoritesSuccessMessage" value="Erfolgreich von Favoritenentfernt">
<input type="hidden" name="removeFromFavoritesFailureMessage" value="Fehler beim Entfernen von Favoriten">
            <!-- These input fields are needed to localize success and failure toasts for removing from a collection-->
<input type="hidden" name="removeFromCollectionSuccessMessage" value="Erfolgreich von Sammlungentfernt">
<input type="hidden" name="removeFromCollectionFailureMessage" value="Fehler beim Entfernen von Sammlung">
    <span id="mark-as-unfinished-button-B081ZF4X1M" class="bc-button
  bc-button-simple
  mark-as-unfinished-button  
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
    <span id="mark-as-finished-button-B081ZF4X1M" class="bc-button
  bc-button-simple
  mark-as-finished-button bc-pub-hidden 
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
    adbl-library-item-select-checkbox adbl-library-item-select-checkbox-B081ZF4X1M">
	<label class="bc-label-wrapper ">
    <div class="bc-checkbox-wrapper">
        <input type="checkbox" autocomplete="off" data-asin="B081ZF4X1M" data-img-url="https://m.media-amazon.com/images/I/61mzizeM6jL._SL500_.jpg" data-img-alt-text="Ghostsitter: Die komplette 5. Staffel Titelbild">
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
    bc-color-secondary" id="time-remaining-finished-B081ZF4X1M">
    Beendet
</span>
</div>
<div id="" class="bc-row-responsive
    adbl-library-item 
    bc-spacing-top-s2" style="">
        <input type="hidden" name="asin" value="B081ZF4X1M">
        <input type="hidden" name="contentType" value="Performance">
        <input type="hidden" name="contentDeliveryType" value="SinglePartBook">
        <input type="hidden" name="asinIndex" value="13">
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
      <a class="bc-button-text" href="https://cds.audible.de/download?asin=B081ZF4X1M&amp;cust_id=YAZn8WqQdJVV1XHpXtUboV2klMsF7_PpGTI_0_-d-9Zccb0V4MMZ0NQpXpfA&amp;codec=LC_128_44100_Stereo&amp;source=Audible&amp;type=AUDI" tabindex="0">
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
                null,
            ],
            '[Game of Thrones - Das Lied von Eis und Feuer] 2 Game of Thrones - Das Lied von Eis und Feuer' => [
                '[Game of Thrones - Das Lied von Eis und Feuer] 2 Game of Thrones - Das Lied von Eis und Feuer',
                '<div id="adbl-library-content-row-B004UZH630" class="adbl-library-content-row">
<div id="" class="bc-row-responsive
    bc-spacing-top-s2" style="">
<div class="bc-col-responsive
    bc-spacing-top-none 
    bc-col-2">
<div id="" class="bc-row-responsive" style="">
                    <a class="bc-link
    bc-color-link" tabindex="0" href="/pd/Game-of-Thrones-Das-Lied-von-Eis-und-Feuer-2-Hoerbuch/B004UZH630?ref=a_library_t_c5_libItem_&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">
<img id="" class="bc-pub-block
    bc-image-inset-border js-only-element" src="https://m.media-amazon.com/images/I/51HzKu8B0ML._SL500_.jpg" alt="Game of Thrones - Das Lied von Eis und Feuer 2 Titelbild">
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
    bc-color-base" tabindex="0" href="/pd/Game-of-Thrones-Das-Lied-von-Eis-und-Feuer-2-Hoerbuch/B004UZH630?ref=a_library_t_c5_libItem_&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">
<span class="bc-text
    bc-size-headline3">Game of Thrones - Das Lied von Eis und Feuer 2</span>
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
    bc-color-base" tabindex="0" href="/search?searchAuthor=George+R.+R.+Martin&amp;ref=a_library_t_c5_libItem_author_14&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">
<span class="bc-text
    bc-size-callout">George R. R. Martin</span>
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
    bc-color-base" tabindex="0" href="/search?searchNarrator=Reinhard+Kuhnert&amp;ref=a_library_t_c5_libItem_narrator_14&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">
<span class="bc-text
    bc-size-callout">Reinhard Kuhnert</span>
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
    bc-color-base" tabindex="0" href="/series/Game-of-Thrones-Das-Lied-von-Eis-und-Feuer-Hoerbuecher/B00FZPH3NM?ref=a_library_t_c5_libItem_series_1&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">Game of Thrones - Das Lied von Eis und Feuer</a>, Titel 2
                            </span>
</li>
<li class="bc-list-item
	rateAndReviewLabel 
    bc-spacing-s0_5">
<div class="bc-box
			bc-box-padding-none
			adbl-prod-rate-review-bar-group
    bc-spacing-top-none">
<input type="hidden" name="asinForRatingStars" value="B004UZH630" class="asin-for-rating-stars">
<input type="hidden" name="ratingsCsrfToken" value="gp43UqhzCX3jjQ1pyiPhSV+5PFPFLM8xTMqduKgAAAABAAAAAGCIcrtyYXcAAAAAFVfwRGgNifE9xfqJS///" class="ratings-csrf-token">
            <div class="bc-trigger
bc-trigger-tooltip">
  <div class="bc-rating-stars adbl-prod-rate-review-bar adbl-prod-rate-review-bar-overall" data-star-count="0" role="radiogroup" tabindex="0" asin="B004UZH630">
      <h3 class="bc-pub-offscreen">interactive rating stars</h3>
      <span class="bc-rating-star" data-index="1" data-text="1 Stern" role="radio" aria-checked="false" aria-label="1 Stern" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="2" data-text="2 Sterne" role="radio" aria-checked="false" aria-label="2 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="3" data-text="3 Sterne" role="radio" aria-checked="false" aria-label="3 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="4" data-text="4 Sterne" role="radio" aria-checked="false" aria-label="4 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="5" data-text="5 Sterne" role="radio" aria-checked="false" aria-label="5 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
  </div>
  <div class="bc-tooltip bc-tooltip-left bc-hidden  bc-palette-inverse bc-rating-stars-tooltip" role="tooltip">
      <div class="bc-tooltip-inner bc-color-background-inverse bc-color-background-base">
          <div class="bc-tooltip-message bc-color-base">
          </div>
      </div>
  </div>
  </div>
    &nbsp;
                <a class="bc-link
    bc-color-base" tabindex="0" href="/write-review?rdpath=%2Fpd%2FB004UZH630&amp;asin=B004UZH630&amp;source=lib&amp;page=2&amp;ref=a_library_t_c5_review&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">Rezension schreiben</a>
</div>
</li>
<li class="bc-list-item
	summaryLabel 
    bc-spacing-s0_5 
    bc-spacing-top-s1">
<span class="bc-text
    merchandisingSummary 
    bc-size-body 
    bc-color-secondary">Die Intrigen und Machtspiele auf dem königlichen Hof gehen weiter und zerreißen Freundschaften und Familien ...</span>
</li>
<li class="bc-list-item">
<input type="hidden" name="isFinished" value="true">
<div class="bc-box
			bc-box-padding-none
			adbl-library-item-button-row
    bc-spacing-top-s1" data-asin="B004UZH630" data-collection-id="">
    <span id="add-to-favorites-button-B004UZH630" class="bc-button
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
    <span id="remove-from-favorites-button-B004UZH630" class="bc-button
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
<input type="hidden" name="collectionIds-B004UZH630" value="[bce793a8-7e62-4749-983a-c36e0b99c7c9]" id="collectionIds-B004UZH630">
            <!-- These input fields are needed to localize success and failure toasts for adding to and removing from favorites-->
<input type="hidden" name="addToFavoritesSuccessMessage" value="Erfolgreich zu Favoriten hinzugefügt">
<input type="hidden" name="addToFavoritesFailureMessage" value="Fehler beim Hinzufügen zu Favoriten">
<input type="hidden" name="removeFromFavoritesSuccessMessage" value="Erfolgreich von Favoritenentfernt">
<input type="hidden" name="removeFromFavoritesFailureMessage" value="Fehler beim Entfernen von Favoriten">
            <!-- These input fields are needed to localize success and failure toasts for removing from a collection-->
<input type="hidden" name="removeFromCollectionSuccessMessage" value="Erfolgreich von Sammlungentfernt">
<input type="hidden" name="removeFromCollectionFailureMessage" value="Fehler beim Entfernen von Sammlung">
    <span id="mark-as-unfinished-button-B004UZH630" class="bc-button
  bc-button-simple
  mark-as-unfinished-button  
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
    <span id="mark-as-finished-button-B004UZH630" class="bc-button
  bc-button-simple
  mark-as-finished-button bc-pub-hidden 
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
    adbl-library-item-select-checkbox adbl-library-item-select-checkbox-B004UZH630">
	<label class="bc-label-wrapper ">
    <div class="bc-checkbox-wrapper">
        <input type="checkbox" autocomplete="off" data-asin="B004UZH630" data-img-url="https://m.media-amazon.com/images/I/51HzKu8B0ML._SL500_.jpg" data-img-alt-text="Game of Thrones - Das Lied von Eis und Feuer 2 Titelbild">
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
    bc-color-secondary" id="time-remaining-finished-B004UZH630">
    Beendet
</span>
</div>
<div id="" class="bc-row-responsive
    adbl-library-item 
    bc-spacing-top-s2" style="">
        <input type="hidden" name="asin" value="B004UZH630">
        <input type="hidden" name="contentType" value="Product">
        <input type="hidden" name="contentDeliveryType" value="MultiPartBook">
        <input type="hidden" name="asinIndex" value="14">
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
      <a class="bc-button-text" target="_blank" href="/companion-file/B004UZH630" tabindex="0">
        <span class="bc-text
    bc-button-text-inner 
    bc-size-action-small">
<span class="bc-text
    bc-size-callout">PDF anzeigen</span>
  </span>
      </a>
    </span>
</div>
<div id="" class="bc-row-responsive
    bc-spacing-top-s2" style="">
<div data-trigger="library-download-popover-B004UZH630" class="bc-trigger
library-download-button
bc-trigger-popover">
    <span class="bc-button
  bc-button-secondary
  adbl-lib-action-download 
  bc-button-small">
      <a class="bc-button-text" href="https://cds.audible.de/download?asin=B004UZH630&amp;cust_id=PjXGczPu119M7uVKTZhWZeDmOhOzYYmw2Yj2nctO_OpsARFa2rai9Xxy6Bwo&amp;codec=LC_128_44100_Stereo&amp;source=Audible&amp;type=AUDI" tabindex="0">
        <span class="bc-text
    bc-button-text-inner 
    bc-size-action-small">
<span class="bc-text
    bc-size-callout">Herunterladen</span>
  </span>
      </a>
    </span>
  </div>
<div id="library-download-popover-B004UZH630" class="bc-popover bc-hidden
    bc-hoverable
    bc-palette-default" role="tooltip" aria-label="Herunterladen" data-popover-position="bottom" data-width="140" data-hoverable="true" data-bodylevel="">
    <span class="bc-popover-beak"></span>
    <div class="bc-popover-inner" style="">
                    <a class="bc-link
    adbl-lib-action-download 
    bc-color-base" tabindex="0" aria-label="HerunterladenKomplett" href="https://cds.audible.de/download?asin=B004UZH630&amp;cust_id=PjXGczPu119M7uVKTZhWZeDmOhOzYYmw2Yj2nctO_OpsARFa2rai9Xxy6Bwo&amp;codec=LC_128_44100_Stereo&amp;source=Audible&amp;type=AUDI">
<div id="" class="bc-row-responsive
    bc-spacing-s2" style="">
<div class="bc-col-responsive
    bc-col-9">
<span class="bc-text
    bc-size-title2">Komplett</span>
</div>
<div class="bc-col-responsive
    bc-col-3">
<i aria-hidden="true" class="bc-icon
	bc-icon-fill-base
	bc-icon-download-s2
	bc-icon-download 
	bc-color-base">
</i>
</div>
</div>
                    </a>
                        <a class="bc-link
    adbl-lib-action-download 
    bc-color-base" tabindex="0" aria-label="HerunterladenTeil 1" href="https://cds.audible.de/download?asin=B004UZH65S&amp;cust_id=NUAiTjmO5BSs_jp9s5Wfp1bzndKmVnhukCDQmAh35AxeSvdnEGjSiUiMXFQ6&amp;codec=LC_128_44100_Stereo&amp;source=Audible&amp;type=AUDI">
<div id="" class="bc-row-responsive
    bc-spacing-s2" style="">
<div class="bc-col-responsive
    bc-col-9">
<span class="bc-text
    bc-size-title2">Teil 1</span>
</div>
<div class="bc-col-responsive
    bc-col-3">
<i aria-hidden="true" class="bc-icon
	bc-icon-fill-base
	bc-icon-download-s2
	bc-icon-download 
	bc-color-base">
</i>
</div>
</div>
                    </a>
                        <a class="bc-link
    adbl-lib-action-download 
    bc-color-base" tabindex="0" aria-label="HerunterladenTeil 2" href="https://cds.audible.de/download?asin=B004UZH68U&amp;cust_id=uIrjwgA_deb5dHoqumXGxOJ1B-3X_8D52B22Ptlumx1PqeYni9a97iPiKtt6&amp;codec=LC_128_44100_Stereo&amp;source=Audible&amp;type=AUDI">
<div id="" class="bc-row-responsive
    bc-spacing-s0" style="">
<div class="bc-col-responsive
    bc-col-9">
<span class="bc-text
    bc-size-title2">Teil 2</span>
</div>
<div class="bc-col-responsive
    bc-col-3">
<i aria-hidden="true" class="bc-icon
	bc-icon-fill-base
	bc-icon-download-s2
	bc-icon-download 
	bc-color-base">
</i>
</div>
</div>
                    </a>
    </div>
</div>
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
                null,
            ],
            '[Woodwalkers] 2 Gefährliche Freundschaft' => [
                '[Woodwalkers] 2 Gefährliche Freundschaft',
                '<div id="adbl-library-content-row-B0798RRSXZ" class="adbl-library-content-row">
<div id="" class="bc-row-responsive
    bc-spacing-top-s2" style="">
<div class="bc-col-responsive
    bc-spacing-top-none 
    bc-col-2">
<div id="" class="bc-row-responsive" style="">
                    <a class="bc-link
    bc-color-link" tabindex="0" href="/pd/Gefaehrliche-Freundschaft-Hoerbuch/B0798RRSXZ?ref=a_library_t_c5_libItem_&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">
<img id="" class="bc-pub-block
    bc-image-inset-border js-only-element" src="https://m.media-amazon.com/images/I/61pXYkXSyYL._SL500_.jpg" alt="Gefährliche Freundschaft Titelbild">
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
    bc-color-base" tabindex="0" href="/pd/Gefaehrliche-Freundschaft-Hoerbuch/B0798RRSXZ?ref=a_library_t_c5_libItem_&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">
<span class="bc-text
    bc-size-headline3">Gefährliche Freundschaft: Woodwalkers 2</span>
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
    bc-color-base" tabindex="0" href="/search?searchAuthor=Katja+Brandis&amp;ref=a_library_t_c5_libItem_author_18&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">
<span class="bc-text
    bc-size-callout">Katja Brandis</span>
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
    bc-color-base" tabindex="0" href="/search?searchNarrator=Timo+Weisschnur&amp;ref=a_library_t_c5_libItem_narrator_18&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">
<span class="bc-text
    bc-size-callout">Timo Weisschnur</span>
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
    bc-color-base" tabindex="0" href="/series/Woodwalkers-Hoerbuecher/B0799NX219?ref=a_library_t_c5_libItem_series_1&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">Woodwalkers</a>, Titel 2
                            </span>
</li>
<li class="bc-list-item
	rateAndReviewLabel 
    bc-spacing-s0_5">
<div class="bc-box
			bc-box-padding-none
			adbl-prod-rate-review-bar-group
    bc-spacing-top-none">
<input type="hidden" name="asinForRatingStars" value="B0798RRSXZ" class="asin-for-rating-stars">
<input type="hidden" name="ratingsCsrfToken" value="guAwNVeHYHRLKjgmjlbtpHLc05Lqd/vOisrtdOEAAAABAAAAAGCIcrtyYXcAAAAAFVfwRGgNifE9xfqJS///" class="ratings-csrf-token">
            <div class="bc-trigger
bc-trigger-tooltip">
  <div class="bc-rating-stars adbl-prod-rate-review-bar adbl-prod-rate-review-bar-overall" data-star-count="0" role="radiogroup" tabindex="0" asin="B0798RRSXZ">
      <h3 class="bc-pub-offscreen">interactive rating stars</h3>
      <span class="bc-rating-star" data-index="1" data-text="1 Stern" role="radio" aria-checked="false" aria-label="1 Stern" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="2" data-text="2 Sterne" role="radio" aria-checked="false" aria-label="2 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="3" data-text="3 Sterne" role="radio" aria-checked="false" aria-label="3 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="4" data-text="4 Sterne" role="radio" aria-checked="false" aria-label="4 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="5" data-text="5 Sterne" role="radio" aria-checked="false" aria-label="5 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
  </div>
  <div class="bc-tooltip bc-tooltip-left bc-hidden  bc-palette-inverse bc-rating-stars-tooltip" role="tooltip">
      <div class="bc-tooltip-inner bc-color-background-inverse bc-color-background-base">
          <div class="bc-tooltip-message bc-color-base">
          </div>
      </div>
  </div>
  </div>
    &nbsp;
                <a class="bc-link
    bc-color-base" tabindex="0" href="/write-review?rdpath=%2Fpd%2FB0798RRSXZ&amp;asin=B0798RRSXZ&amp;source=lib&amp;page=2&amp;ref=a_library_t_c5_review&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N4JJYTQB5STNR6SH20ME">Rezension schreiben</a>
</div>
</li>
<li class="bc-list-item
	summaryLabel 
    bc-spacing-s0_5 
    bc-spacing-top-s1">
<span class="bc-text
    merchandisingSummary 
    bc-size-body 
    bc-color-secondary">Es ist Winter in den Rocky Mountains und Carag und seine Mitschüler stürzen sich voller Freude in ihre ersten Lernexpeditionen...</span>
</li>
<li class="bc-list-item">
<input type="hidden" name="isFinished" value="true">
<div class="bc-box
			bc-box-padding-none
			adbl-library-item-button-row
    bc-spacing-top-s1" data-asin="B0798RRSXZ" data-collection-id="">
    <span id="add-to-favorites-button-B0798RRSXZ" class="bc-button
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
    <span id="remove-from-favorites-button-B0798RRSXZ" class="bc-button
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
<input type="hidden" name="collectionIds-B0798RRSXZ" value="[3bb31f7e-d16e-4c4c-8cb7-6659cfa48854]" id="collectionIds-B0798RRSXZ">
            <!-- These input fields are needed to localize success and failure toasts for adding to and removing from favorites-->
<input type="hidden" name="addToFavoritesSuccessMessage" value="Erfolgreich zu Favoriten hinzugefügt">
<input type="hidden" name="addToFavoritesFailureMessage" value="Fehler beim Hinzufügen zu Favoriten">
<input type="hidden" name="removeFromFavoritesSuccessMessage" value="Erfolgreich von Favoritenentfernt">
<input type="hidden" name="removeFromFavoritesFailureMessage" value="Fehler beim Entfernen von Favoriten">
            <!-- These input fields are needed to localize success and failure toasts for removing from a collection-->
<input type="hidden" name="removeFromCollectionSuccessMessage" value="Erfolgreich von Sammlungentfernt">
<input type="hidden" name="removeFromCollectionFailureMessage" value="Fehler beim Entfernen von Sammlung">
    <span id="mark-as-unfinished-button-B0798RRSXZ" class="bc-button
  bc-button-simple
  mark-as-unfinished-button  
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
    <span id="mark-as-finished-button-B0798RRSXZ" class="bc-button
  bc-button-simple
  mark-as-finished-button bc-pub-hidden 
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
    adbl-library-item-select-checkbox adbl-library-item-select-checkbox-B0798RRSXZ">
	<label class="bc-label-wrapper ">
    <div class="bc-checkbox-wrapper">
        <input type="checkbox" autocomplete="off" data-asin="B0798RRSXZ" data-img-url="https://m.media-amazon.com/images/I/61pXYkXSyYL._SL500_.jpg" data-img-alt-text="Gefährliche Freundschaft Titelbild">
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
    bc-color-secondary" id="time-remaining-finished-B0798RRSXZ">
    Beendet
</span>
</div>
<div id="" class="bc-row-responsive
    adbl-library-item 
    bc-spacing-top-s2" style="">
        <input type="hidden" name="asin" value="B0798RRSXZ">
        <input type="hidden" name="contentType" value="Product">
        <input type="hidden" name="contentDeliveryType" value="SinglePartBook">
        <input type="hidden" name="asinIndex" value="18">
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
      <a class="bc-button-text" href="https://cds.audible.de/download?asin=B0798RRSXZ&amp;cust_id=Z2nckQqJ0PFXUk9S_hLtVFQ-SpKbtC0TvbCIgieOoZZ33JaMG_SeEEqrfPv3&amp;codec=LC_128_44100_Stereo&amp;source=Audible&amp;type=AUDI" tabindex="0">
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
                null,
            ],
            'Der Zombie Survival Guide - Überleben unter Untoten' => [
                'Der Zombie Survival Guide - Überleben unter Untoten',
                '<div id="adbl-library-content-row-B005LNBKRS" class="adbl-library-content-row">
<div id="" class="bc-row-responsive
    bc-spacing-top-s2" style="">
<div class="bc-col-responsive
    bc-spacing-top-none 
    bc-col-2">
<div id="" class="bc-row-responsive" style="">
                    <a class="bc-link
    bc-color-link" tabindex="0" href="/pd/Der-Zombie-Survival-Guide-Hoerbuch/B005LNBKRS?ref=a_library_t_c5_libItem_&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N414ZZ5YJ63NR1RM9QK7">
<img id="" class="bc-pub-block
    bc-image-inset-border js-only-element" src="https://m.media-amazon.com/images/I/517hgPUrYDL._SL500_.jpg" alt="Der Zombie Survival Guide Titelbild">
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
    bc-color-base" tabindex="0" href="/pd/Der-Zombie-Survival-Guide-Hoerbuch/B005LNBKRS?ref=a_library_t_c5_libItem_&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N414ZZ5YJ63NR1RM9QK7">
<span class="bc-text
    bc-size-headline3">Der Zombie Survival Guide: Überleben unter Untoten</span>
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
    bc-color-base" tabindex="0" href="/search?searchAuthor=Max+Brooks&amp;ref=a_library_t_c5_libItem_author_6&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N414ZZ5YJ63NR1RM9QK7">
<span class="bc-text
    bc-size-callout">Max Brooks</span>
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
    bc-color-base" tabindex="0" href="/search?searchNarrator=David+Nathan&amp;ref=a_library_t_c5_libItem_narrator_6&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N414ZZ5YJ63NR1RM9QK7">
<span class="bc-text
    bc-size-callout">David Nathan</span>
                                        </a>
                            </span>
</li>
<li class="bc-list-item
	rateAndReviewLabel 
    bc-spacing-s0_5">
<div class="bc-box
			bc-box-padding-none
			adbl-prod-rate-review-bar-group
    bc-spacing-top-none">
<input type="hidden" name="asinForRatingStars" value="B005LNBKRS" class="asin-for-rating-stars">
<input type="hidden" name="ratingsCsrfToken" value="gu2A5KzGc7WvI1t2uJOfYXZKOU28g5lpB3JOoHkAAAABAAAAAGCIdW1yYXcAAAAAFVfwRGgNifE9xfqJS///" class="ratings-csrf-token">
            <div class="bc-trigger
bc-trigger-tooltip">
  <div class="bc-rating-stars adbl-prod-rate-review-bar adbl-prod-rate-review-bar-overall" data-star-count="0" role="radiogroup" tabindex="0" asin="B005LNBKRS">
      <h3 class="bc-pub-offscreen">interactive rating stars</h3>
      <span class="bc-rating-star" data-index="1" data-text="1 Stern" role="radio" aria-checked="false" aria-label="1 Stern" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="2" data-text="2 Sterne" role="radio" aria-checked="false" aria-label="2 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="3" data-text="3 Sterne" role="radio" aria-checked="false" aria-label="3 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="4" data-text="4 Sterne" role="radio" aria-checked="false" aria-label="4 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="5" data-text="5 Sterne" role="radio" aria-checked="false" aria-label="5 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
  </div>
  <div class="bc-tooltip bc-tooltip-left bc-hidden  bc-palette-inverse bc-rating-stars-tooltip" role="tooltip">
      <div class="bc-tooltip-inner bc-color-background-inverse bc-color-background-base">
          <div class="bc-tooltip-message bc-color-base">
          </div>
      </div>
  </div>
  </div>
    &nbsp;
                <a class="bc-link
    bc-color-base" tabindex="0" href="/write-review?rdpath=%2Fpd%2FB005LNBKRS&amp;asin=B005LNBKRS&amp;source=lib&amp;page=3&amp;ref=a_library_t_c5_review&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=N414ZZ5YJ63NR1RM9QK7">Rezension schreiben</a>
</div>
</li>
<li class="bc-list-item
	summaryLabel 
    bc-spacing-s0_5 
    bc-spacing-top-s1">
<span class="bc-text
    merchandisingSummary 
    bc-size-body 
    bc-color-secondary">Der Zombie Survival Guide ist der Schlüssel zur erfolgreichen Abwehr von Untoten, die eine ständige Bedrohung für den Menschen darstellen...</span>
</li>
<li class="bc-list-item">
<input type="hidden" name="isFinished" value="false">
<div class="bc-box
			bc-box-padding-none
			adbl-library-item-button-row
    bc-spacing-top-s1" data-asin="B005LNBKRS" data-collection-id="">
    <span id="add-to-favorites-button-B005LNBKRS" class="bc-button
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
    <span id="remove-from-favorites-button-B005LNBKRS" class="bc-button
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
<input type="hidden" name="collectionIds-B005LNBKRS" value="[]" id="collectionIds-B005LNBKRS">
            <!-- These input fields are needed to localize success and failure toasts for adding to and removing from favorites-->
<input type="hidden" name="addToFavoritesSuccessMessage" value="Erfolgreich zu Favoriten hinzugefügt">
<input type="hidden" name="addToFavoritesFailureMessage" value="Fehler beim Hinzufügen zu Favoriten">
<input type="hidden" name="removeFromFavoritesSuccessMessage" value="Erfolgreich von Favoritenentfernt">
<input type="hidden" name="removeFromFavoritesFailureMessage" value="Fehler beim Entfernen von Favoriten">
            <!-- These input fields are needed to localize success and failure toasts for removing from a collection-->
<input type="hidden" name="removeFromCollectionSuccessMessage" value="Erfolgreich von Sammlungentfernt">
<input type="hidden" name="removeFromCollectionFailureMessage" value="Fehler beim Entfernen von Sammlung">
    <span id="mark-as-unfinished-button-B005LNBKRS" class="bc-button
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
    <span id="mark-as-finished-button-B005LNBKRS" class="bc-button
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
    adbl-library-item-select-checkbox adbl-library-item-select-checkbox-B005LNBKRS">
	<label class="bc-label-wrapper ">
    <div class="bc-checkbox-wrapper">
        <input type="checkbox" autocomplete="off" data-asin="B005LNBKRS" data-img-url="https://m.media-amazon.com/images/I/517hgPUrYDL._SL500_.jpg" data-img-alt-text="Der Zombie Survival Guide Titelbild">
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
    bc-color-secondary" id="time-remaining-finished-B005LNBKRS">
    Beendet
</span>
<div id="time-remaining-display-B005LNBKRS" class="bc-row-responsive" style="">
<div class="bc-col-responsive
    no-padding-right 
    bc-spacing-top-s1 
    bc-text-right 
    bc-col-5">
<div class="bc-meter bc-color-background-tertiary" style="height:5px">
    <div role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="18" class="bc-meter-bar bc-color-background-progress" style="width:18%"></div>
</div>
</div>
<div class="bc-col-responsive
    small-padding-left 
    bc-text-left 
    bc-col-7">
<span class="bc-text
    bc-color-secondary">7 Std. 28 Min. verbleibend</span>
</div>
</div>
</div>
<div id="" class="bc-row-responsive
    adbl-library-item 
    bc-spacing-top-s2" style="">
        <input type="hidden" name="asin" value="B005LNBKRS">
        <input type="hidden" name="contentType" value="Product">
        <input type="hidden" name="contentDeliveryType" value="MultiPartBook">
        <input type="hidden" name="asinIndex" value="6">
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
<div data-trigger="library-download-popover-B005LNBKRS" class="bc-trigger
library-download-button
bc-trigger-popover">
    <span class="bc-button
  bc-button-secondary
  adbl-lib-action-download 
  bc-button-small">
      <a class="bc-button-text" href="https://cds.audible.de/download?asin=B005LNBKRS&amp;cust_id=peylTLl8hi1ii9bwvOPvzy7T6btiKUSPoYFZwkgAP4pH4N41pEPRr2plf7Qu&amp;codec=LC_128_44100_Stereo&amp;source=Audible&amp;type=AUDI" tabindex="0">
        <span class="bc-text
    bc-button-text-inner 
    bc-size-action-small">
<span class="bc-text
    bc-size-callout">Herunterladen</span>
  </span>
      </a>
    </span>
  </div>
<div id="library-download-popover-B005LNBKRS" class="bc-popover bc-hidden
    bc-hoverable
    bc-palette-default" role="tooltip" aria-label="Herunterladen" data-popover-position="bottom" data-width="140" data-hoverable="true" data-bodylevel="">
    <span class="bc-popover-beak"></span>
    <div class="bc-popover-inner" style="">
                    <a class="bc-link
    adbl-lib-action-download 
    bc-color-base" tabindex="0" aria-label="HerunterladenKomplett" href="https://cds.audible.de/download?asin=B005LNBKRS&amp;cust_id=peylTLl8hi1ii9bwvOPvzy7T6btiKUSPoYFZwkgAP4pH4N41pEPRr2plf7Qu&amp;codec=LC_128_44100_Stereo&amp;source=Audible&amp;type=AUDI">
<div id="" class="bc-row-responsive
    bc-spacing-s2" style="">
<div class="bc-col-responsive
    bc-col-9">
<span class="bc-text
    bc-size-title2">Komplett</span>
</div>
<div class="bc-col-responsive
    bc-col-3">
<i aria-hidden="true" class="bc-icon
	bc-icon-fill-base
	bc-icon-download-s2
	bc-icon-download 
	bc-color-base">
</i>
</div>
</div>
                    </a>
                        <a class="bc-link
    adbl-lib-action-download 
    bc-color-base" tabindex="0" aria-label="HerunterladenTeil 1" href="https://cds.audible.de/download?asin=B005LNBLE0&amp;cust_id=OYEYvf35kke4yiwS70R3KNeyupT89DIQvS5f-4-0zuGR1h0e8aGCxPM6Q9-g&amp;codec=LC_128_44100_Stereo&amp;source=Audible&amp;type=AUDI">
<div id="" class="bc-row-responsive
    bc-spacing-s2" style="">
<div class="bc-col-responsive
    bc-col-9">
<span class="bc-text
    bc-size-title2">Teil 1</span>
</div>
<div class="bc-col-responsive
    bc-col-3">
<i aria-hidden="true" class="bc-icon
	bc-icon-fill-base
	bc-icon-download-s2
	bc-icon-download 
	bc-color-base">
</i>
</div>
</div>
                    </a>
                        <a class="bc-link
    adbl-lib-action-download 
    bc-color-base" tabindex="0" aria-label="HerunterladenTeil 2" href="https://cds.audible.de/download?asin=B005LNBLPE&amp;cust_id=MRSQjO2MJ7ZiJCSMYwrzJ6YDuMIDGdS3CLjeXOCG2OG4oIXYvLUo4RPCEndJ&amp;codec=LC_128_44100_Stereo&amp;source=Audible&amp;type=AUDI">
<div id="" class="bc-row-responsive
    bc-spacing-s0" style="">
<div class="bc-col-responsive
    bc-col-9">
<span class="bc-text
    bc-size-title2">Teil 2</span>
</div>
<div class="bc-col-responsive
    bc-col-3">
<i aria-hidden="true" class="bc-icon
	bc-icon-fill-base
	bc-icon-download-s2
	bc-icon-download 
	bc-color-base">
</i>
</div>
</div>
                    </a>
    </div>
</div>
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
                null,
            ],
            'ALIEN - In den Schatten - Die 1. Staffel (Kostenlose Hörprobe)' => [
                'ALIEN - In den Schatten - Die 1. Staffel (Kostenlose Hörprobe)',
                '<div id="adbl-library-content-row-B07RDQSFD8" class="adbl-library-content-row">
<div id="" class="bc-row-responsive
    bc-spacing-top-s2" style="">
<div class="bc-col-responsive
    bc-spacing-top-none 
    bc-col-2">
<div id="" class="bc-row-responsive" style="">
                    <a class="bc-link
    bc-color-link" tabindex="0" href="/pd/ALIEN-In-den-Schatten-Die-1-Staffel-Kostenlose-Hoerprobe-Hoerbuch/B07RDQSFD8?ref=a_library_t_c5_libItem_&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=4963226C6XMFGPH1MW14">
<img id="" class="bc-pub-block
    bc-image-inset-border js-only-element" src="https://m.media-amazon.com/images/I/41QQiDJ0qKL._SL500_.jpg" alt="ALIEN - In den Schatten: Die 1. Staffel (Kostenlose Hörprobe) Titelbild">
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
    bc-color-base" tabindex="0" href="/pd/ALIEN-In-den-Schatten-Die-1-Staffel-Kostenlose-Hoerprobe-Hoerbuch/B07RDQSFD8?ref=a_library_t_c5_libItem_&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=4963226C6XMFGPH1MW14">
<span class="bc-text
    bc-size-headline3">ALIEN - In den Schatten: Die 1. Staffel (Kostenlose Hörprobe)</span>
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
    bc-color-base" tabindex="0" href="/search?searchAuthor=Tim+Lebbon&amp;ref=a_library_t_c5_libItem_author_18&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=4963226C6XMFGPH1MW14">
<span class="bc-text
    bc-size-callout">Tim Lebbon</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/author/Dirk-Maggs/B07RJ1RB8T?ref=a_library_t_c5_libItem_author_18&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=4963226C6XMFGPH1MW14">
<span class="bc-text
    bc-size-callout">Dirk Maggs</span>
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
    bc-color-base" tabindex="0" href="/search?searchNarrator=Karin+Buchholz&amp;ref=a_library_t_c5_libItem_narrator_18&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=4963226C6XMFGPH1MW14">
<span class="bc-text
    bc-size-callout">Karin Buchholz</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=Dietmar+Wunder&amp;ref=a_library_t_c5_libItem_narrator_18&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=4963226C6XMFGPH1MW14">
<span class="bc-text
    bc-size-callout">Dietmar Wunder</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=Michael+Iwannek&amp;ref=a_library_t_c5_libItem_narrator_18&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=4963226C6XMFGPH1MW14">
<span class="bc-text
    bc-size-callout">Michael Iwannek</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=Ann+Vielhaben&amp;ref=a_library_t_c5_libItem_narrator_18&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=4963226C6XMFGPH1MW14">
<span class="bc-text
    bc-size-callout">Ann Vielhaben</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=Bernd+Vollbrecht&amp;ref=a_library_t_c5_libItem_narrator_18&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=4963226C6XMFGPH1MW14">
<span class="bc-text
    bc-size-callout">Bernd Vollbrecht</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=David+Nathan&amp;ref=a_library_t_c5_libItem_narrator_18&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=4963226C6XMFGPH1MW14">
<span class="bc-text
    bc-size-callout">David Nathan</span>
                                        </a>
                            </span>
</li>
<li class="bc-list-item
	rateAndReviewLabel 
    bc-spacing-s0_5">
<div class="bc-box
			bc-box-padding-none
			adbl-prod-rate-review-bar-group
    bc-spacing-top-none">
<input type="hidden" name="asinForRatingStars" value="B07RDQSFD8" class="asin-for-rating-stars">
<input type="hidden" name="ratingsCsrfToken" value="gpOckBkmgUnOjnJEzJN2/tYlaVJWqb9NYKQWj44AAAABAAAAAGCJadtyYXcAAAAAFVfwRGgNifE9xfqJS///" class="ratings-csrf-token">
            <div class="bc-trigger
bc-trigger-tooltip">
  <div class="bc-rating-stars adbl-prod-rate-review-bar adbl-prod-rate-review-bar-overall" data-star-count="0" role="radiogroup" tabindex="0" asin="B07RDQSFD8" aria-describedby="tooltip1619619861587">
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
<i aria-hidden="true" class="bc-icon bc-icon-fill-tertiary bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-tertiary">
</i>
      </span>
  </div>
  <div class="bc-tooltip bc-tooltip-left bc-palette-inverse bc-rating-stars-tooltip bc-hidden" role="tooltip" id="tooltip1619619861587" style="top: -7px;">
      <div class="bc-tooltip-inner bc-color-background-inverse bc-color-background-base">
          <div class="bc-tooltip-message bc-color-base">4 Sterne</div>
      </div>
  </div>
  </div>
    &nbsp;
                <a class="bc-link
    bc-color-base" tabindex="0" href="/write-review?rdpath=%2Fpd%2FB07RDQSFD8&amp;asin=B07RDQSFD8&amp;source=lib&amp;page=4&amp;ref=a_library_t_c5_review&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=4963226C6XMFGPH1MW14">Rezension schreiben</a>
</div>
</li>
<li class="bc-list-item
	summaryLabel 
    bc-spacing-s0_5 
    bc-spacing-top-s1">
<span class="bc-text
    merchandisingSummary 
    bc-size-body 
    bc-color-secondary">Der Raumfrachter MARION umkreist den Planeten LV178, aus dessen Minen das wertvolle Element Trimonit gewonnen werden soll...</span>
</li>
<li class="bc-list-item">
<input type="hidden" name="isFinished" value="false">
<div class="bc-box
			bc-box-padding-none
			adbl-library-item-button-row
    bc-spacing-top-s1" data-asin="B07RDQSFD8" data-collection-id="">
    <span id="add-to-favorites-button-B07RDQSFD8" class="bc-button
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
    <span id="remove-from-favorites-button-B07RDQSFD8" class="bc-button
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
<input type="hidden" name="collectionIds-B07RDQSFD8" value="" id="collectionIds-B07RDQSFD8">
            <!-- These input fields are needed to localize success and failure toasts for adding to and removing from favorites-->
<input type="hidden" name="addToFavoritesSuccessMessage" value="Erfolgreich zu Favoriten hinzugefügt">
<input type="hidden" name="addToFavoritesFailureMessage" value="Fehler beim Hinzufügen zu Favoriten">
<input type="hidden" name="removeFromFavoritesSuccessMessage" value="Erfolgreich von Favoritenentfernt">
<input type="hidden" name="removeFromFavoritesFailureMessage" value="Fehler beim Entfernen von Favoriten">
            <!-- These input fields are needed to localize success and failure toasts for removing from a collection-->
<input type="hidden" name="removeFromCollectionSuccessMessage" value="Erfolgreich von Sammlungentfernt">
<input type="hidden" name="removeFromCollectionFailureMessage" value="Fehler beim Entfernen von Sammlung">
    <span id="mark-as-unfinished-button-B07RDQSFD8" class="bc-button
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
    <span id="mark-as-finished-button-B07RDQSFD8" class="bc-button
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
    adbl-library-item-select-checkbox adbl-library-item-select-checkbox-B07RDQSFD8">
	<label class="bc-label-wrapper ">
    <div class="bc-checkbox-wrapper">
        <input type="checkbox" autocomplete="off" data-asin="B07RDQSFD8" data-img-url="https://m.media-amazon.com/images/I/41QQiDJ0qKL._SL500_.jpg" data-img-alt-text="ALIEN - In den Schatten: Die 1. Staffel (Kostenlose Hörprobe) Titelbild">
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
    bc-color-secondary" id="time-remaining-finished-B07RDQSFD8">
    Beendet
</span>
<div id="time-remaining-display-B07RDQSFD8" class="bc-row-responsive" style="">
<div class="bc-col-responsive">
<span class="bc-text
    bc-color-secondary">1 Std.</span>
</div>
</div>
</div>
<div id="" class="bc-row-responsive
    adbl-library-item 
    bc-spacing-top-s2" style="">
        <input type="hidden" name="asin" value="B07RDQSFD8">
        <input type="hidden" name="contentType" value="Performance">
        <input type="hidden" name="contentDeliveryType" value="SinglePartBook">
        <input type="hidden" name="asinIndex" value="18">
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
      <a class="bc-button-text" href="https://cds.audible.de/download?asin=B07RDQSFD8&amp;cust_id=rXQbqMAZfHV7AJTFEcB9c__4HdLDWXry6pT-g7ZwqNjxpRYdxMNZhgmdJbwk&amp;codec=LC_128_44100_Stereo&amp;source=Audible&amp;type=AUDI" tabindex="0">
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
                null,
            ],
            '[Sag mal, du als Physiker. Der P.M.-Podcast: Staffel 5 (Original Podcast)] Flg. 22 - Frieren ohne den (Golf-)Strom' => [
                '[Sag mal, du als Physiker. Der P.M.-Podcast: Staffel 5 (Original Podcast)] Flg. 22 - Frieren ohne den (Golf-)Strom',
                '<div id="adbl-library-content-row-B08NC7JHV7" class="adbl-library-content-row">
<div id="" class="bc-row-responsive
    bc-spacing-top-s2" style="">
<div class="bc-col-responsive
    bc-spacing-top-none 
    bc-col-2">
<div id="" class="bc-row-responsive" style="">
<img id="" class="bc-pub-block
    bc-image-inset-border js-only-element" src="https://m.media-amazon.com/images/I/51SG5WHclhL._SL500_.jpg" alt="Sag mal, du als Physiker. Der P.M.-Podcast: Staffel 5 (Original Podcast) Titelbild">
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
<span class="bc-text
    bc-size-headline3">Sag mal, du als Physiker. Der P.M.-Podcast: Staffel 5 (Original Podcast)</span>
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
    bc-color-base" tabindex="0" href="/search?searchAuthor=Sag+mal+du+als+Physiker.+Der+P.M.-Podcast&amp;ref=a_library_t_c5_libItem_author_5&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=X22AZV0Y7G22Z49Z0DP1">
<span class="bc-text
    bc-size-callout">Sag mal du als Physiker. Der P.M.-Podcast</span>
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
    bc-color-base" tabindex="0" href="/search?searchNarrator=Jens+Schr%C3%B6der&amp;ref=a_library_t_c5_libItem_narrator_5&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=X22AZV0Y7G22Z49Z0DP1">
<span class="bc-text
    bc-size-callout">Jens Schröder</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=Johannes+K%C3%BCckens&amp;ref=a_library_t_c5_libItem_narrator_5&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=X22AZV0Y7G22Z49Z0DP1">
<span class="bc-text
    bc-size-callout">Johannes Kückens</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=Michael+B%C3%BCker&amp;ref=a_library_t_c5_libItem_narrator_5&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=X22AZV0Y7G22Z49Z0DP1">
<span class="bc-text
    bc-size-callout">Michael Büker</span>
                                        </a>
                            </span>
</li>
<li class="bc-list-item
	summaryLabel 
    bc-spacing-s0_5 
    bc-spacing-top-s1">
<span class="bc-text
    merchandisingSummary 
    bc-size-body 
    bc-color-secondary"><p>"Sag mal, du als Physiker. Der P.M.-Podcast" beantwortet die großen und kleinen Fragen der Physik in unserem Alltag.</p></span>
</li>
<li class="bc-list-item">
<input type="hidden" name="isFinished" value="false">
<div class="bc-box
			bc-box-padding-none
			adbl-library-item-button-row
    bc-spacing-top-s1" data-asin="B08NC7JHV7" data-collection-id="">
</div>
</li>
                </ul>
</span>
</div>
<div class="bc-col-responsive
    bc-col-1 
    bc-col-offset-2">
<div id="" class="bc-row-responsive
    adbl-episodes-link" style="">
                            <input type="hidden" name="asin" value="B08NC7JHV7">
                            <input type="hidden" name="contentType" value="Show">
                            <input type="hidden" name="contentDeliveryType" value="Periodical">
                            <input type="hidden" name="asinIndex" value="5">
                            <a class="bc-link
    bc-color-base 
    bc-text-normal" tabindex="0" aria-label="Sag mal, du als Physiker. Der P.M.-Podcast: Staffel 5 (Original Podcast)Alle Folgen zeigen" href="/library/episodes?parentAsin=B08NC7JHV7&amp;parentTitle=Sag+mal%2c+du+als+Physiker.+Der+P.M.-Podcast%3a+Staffel+5+(Original+Podcast)&amp;ref=a_library_t_c5_view_all_episodes_5&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=X22AZV0Y7G22Z49Z0DP1">
<div style="height: 171px;" class="bc-box
			bc-box-padding-base
			chevron-container">
<i style="vertical-align: center" aria-hidden="true" class="bc-icon
	bc-icon-fill-base
	bc-icon-chevron-right-s4
	bc-icon-chevron-right 
	bc-icon-size-large 
	bc-color-base">
</i>
</div>
                            </a>
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
                '<div id="adbl-library-content-row-B08NCXHN17" class="adbl-library-content-row">
<div id="" class="bc-row-responsive
    bc-spacing-top-s2" style="">
<div class="bc-col-responsive
    bc-spacing-top-none 
    bc-col-2">
<div id="" class="bc-row-responsive" style="">
                    <a class="bc-link
    bc-color-link" tabindex="0" href="/pd/Flg-22-Frieren-ohne-den-Golf-Strom-Hoerbuch/B08NCXHN17?ref=a_library_e_c5_libItem_&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=6SN8WX3X7TEJTTX1XP92">
<img id="" class="bc-pub-block
    bc-image-inset-border js-only-element" src="https://m.media-amazon.com/images/I/51SG5WHclhL._SL500_.jpg" alt="Flg. 22 - Frieren ohne den (Golf-)Strom Titelbild">
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
    bc-color-base" tabindex="0" href="/pd/Flg-22-Frieren-ohne-den-Golf-Strom-Hoerbuch/B08NCXHN17?ref=a_library_e_c5_libItem_&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=6SN8WX3X7TEJTTX1XP92">
<span class="bc-text
    bc-size-headline3">Flg. 22 - Frieren ohne den (Golf-)Strom</span>
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
    bc-color-base" tabindex="0" href="/search?searchAuthor=Sag+mal+du+als+Physiker.+Der+P.M.-Podcast&amp;ref=a_library_e_c5_libItem_author_1&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=6SN8WX3X7TEJTTX1XP92">
<span class="bc-text
    bc-size-callout">Sag mal du als Physiker. Der P.M.-Podcast</span>
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
    bc-color-base" tabindex="0" href="/search?searchNarrator=Jens+Schr%C3%B6der&amp;ref=a_library_e_c5_libItem_narrator_1&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=6SN8WX3X7TEJTTX1XP92">
<span class="bc-text
    bc-size-callout">Jens Schröder</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=Johannes+K%C3%BCckens&amp;ref=a_library_e_c5_libItem_narrator_1&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=6SN8WX3X7TEJTTX1XP92">
<span class="bc-text
    bc-size-callout">Johannes Kückens</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=Michael+B%C3%BCker&amp;ref=a_library_e_c5_libItem_narrator_1&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=6SN8WX3X7TEJTTX1XP92">
<span class="bc-text
    bc-size-callout">Michael Büker</span>
                                        </a>
                            </span>
</li>
<li class="bc-list-item
	rateAndReviewLabel 
    bc-spacing-s0_5">
<div class="bc-box
			bc-box-padding-none
			adbl-prod-rate-review-bar-group
    bc-spacing-top-none">
<input type="hidden" name="asinForRatingStars" value="B08NCXHN17" class="asin-for-rating-stars">
<input type="hidden" name="ratingsCsrfToken" value="grck5zzylXnkYwQA0C07kKO3Ownw7gOjpijF0+cAAAABAAAAAGCIgllyYXcAAAAAFVfwRGgNifE9xfqJS///" class="ratings-csrf-token">
            <div class="bc-trigger
bc-trigger-tooltip">
  <div class="bc-rating-stars adbl-prod-rate-review-bar adbl-prod-rate-review-bar-overall" data-star-count="0" role="radiogroup" tabindex="0" asin="B08NCXHN17">
      <h3 class="bc-pub-offscreen">interactive rating stars</h3>
      <span class="bc-rating-star" data-index="1" data-text="1 Stern" role="radio" aria-checked="false" aria-label="1 Stern" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="2" data-text="2 Sterne" role="radio" aria-checked="false" aria-label="2 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="3" data-text="3 Sterne" role="radio" aria-checked="false" aria-label="3 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="4" data-text="4 Sterne" role="radio" aria-checked="false" aria-label="4 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="5" data-text="5 Sterne" role="radio" aria-checked="false" aria-label="5 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
  </div>
  <div class="bc-tooltip bc-tooltip-left bc-hidden  bc-palette-inverse bc-rating-stars-tooltip" role="tooltip">
      <div class="bc-tooltip-inner bc-color-background-inverse bc-color-background-base">
          <div class="bc-tooltip-message bc-color-base">
          </div>
      </div>
  </div>
  </div>
    &nbsp;
                <a class="bc-link
    bc-color-base" tabindex="0" href="/write-review?rdpath=%2Fpd%2FB08NCXHN17&amp;asin=B08NCXHN17&amp;source=lib&amp;page=1&amp;ref=a_library_e_c5_review&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=6SN8WX3X7TEJTTX1XP92">Rezension schreiben</a>
</div>
</li>
<li class="bc-list-item
	summaryLabel 
    bc-spacing-s0_5 
    bc-spacing-top-s1">
<span class="bc-text
    merchandisingSummary 
    bc-size-body 
    bc-color-secondary"><p>"Sag mal, du als Physiker. Der P.M.-Podcast" beantwortet die großen und kleinen Fragen der Physik in unserem Alltag.</p></span>
</li>
<li class="bc-list-item">
<input type="hidden" name="isFinished" value="false">
<div class="bc-box
			bc-box-padding-none
			adbl-library-item-button-row
    bc-spacing-top-s1" data-asin="B08NCXHN17" data-collection-id="">
    <span id="add-to-favorites-button-B08NCXHN17" class="bc-button
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
    <span id="remove-from-favorites-button-B08NCXHN17" class="bc-button
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
<input type="hidden" name="collectionIds-B08NCXHN17" value="" id="collectionIds-B08NCXHN17">
            <!-- These input fields are needed to localize success and failure toasts for adding to and removing from favorites-->
<input type="hidden" name="addToFavoritesSuccessMessage" value="Erfolgreich zu Favoriten hinzugefügt">
<input type="hidden" name="addToFavoritesFailureMessage" value="Fehler beim Hinzufügen zu Favoriten">
<input type="hidden" name="removeFromFavoritesSuccessMessage" value="Erfolgreich von Favoritenentfernt">
<input type="hidden" name="removeFromFavoritesFailureMessage" value="Fehler beim Entfernen von Favoriten">
            <!-- These input fields are needed to localize success and failure toasts for removing from a collection-->
<input type="hidden" name="removeFromCollectionSuccessMessage" value="Erfolgreich von Sammlungentfernt">
<input type="hidden" name="removeFromCollectionFailureMessage" value="Fehler beim Entfernen von Sammlung">
    <span id="mark-as-unfinished-button-B08NCXHN17" class="bc-button
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
    <span id="mark-as-finished-button-B08NCXHN17" class="bc-button
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
    adbl-library-item-select-checkbox adbl-library-item-select-checkbox-B08NCXHN17">
	<label class="bc-label-wrapper ">
    <div class="bc-checkbox-wrapper">
        <input type="checkbox" autocomplete="off" data-asin="B08NCXHN17" data-img-url="https://m.media-amazon.com/images/I/51SG5WHclhL._SL500_.jpg" data-img-alt-text="Flg. 22 - Frieren ohne den (Golf-)Strom Titelbild">
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
    bc-color-secondary" id="time-remaining-finished-B08NCXHN17">
    Beendet
</span>
<div id="time-remaining-display-B08NCXHN17" class="bc-row-responsive" style="">
<div class="bc-col-responsive">
<span class="bc-text
    bc-color-secondary">44 Min.</span>
</div>
</div>
</div>
<div id="" class="bc-row-responsive
    adbl-library-item 
    bc-spacing-top-s2" style="">
        <input type="hidden" name="asin" value="B08NCXHN17">
        <input type="hidden" name="contentType" value="Show">
        <input type="hidden" name="contentDeliveryType" value="SinglePartIssue">
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
      <a class="bc-button-text" href="https://cds.audible.de/download?asin=B08NCXHN17&amp;cust_id=Mr7I8HTlOCvWrZ5yabMa1MdwrcnaeuQlvaBxbxGBwqZvia9QmyOmoJV2xD1O&amp;codec=LC_128_44100_Stereo&amp;source=Audible&amp;type=AUDI" tabindex="0">
        <span class="bc-text
    bc-button-text-inner 
    bc-size-action-small">
<span class="bc-text
    bc-size-callout">Herunterladen</span>
  </span>
      </a>
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
            '[Paw Patrol] 128-130 Der Sternschnuppen-Regen' => [
                '[Paw Patrol] 128-130 Der Sternschnuppen-Regen',
                '<div id="adbl-library-content-row-B08WM3F85K" class="adbl-library-content-row">
<div id="" class="bc-row-responsive
    bc-spacing-top-s2" style="">
<div class="bc-col-responsive
    bc-spacing-top-none 
    bc-col-2">
<div id="" class="bc-row-responsive" style="">
                    <a class="bc-link
    bc-color-link" tabindex="0" href="/pd/Der-Sternschnuppen-Regen-Hoerbuch/B08WM3F85K?ref=a_library_t_c5_libItem_&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=EZVGAWPQKM9ET3CPHQ44">
<img id="" class="bc-pub-block
    bc-image-inset-border js-only-element" src="https://m.media-amazon.com/images/I/51Upaz80RaL._SL500_.jpg" alt="Der Sternschnuppen-Regen Titelbild">
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
    bc-color-base" tabindex="0" href="/pd/Der-Sternschnuppen-Regen-Hoerbuch/B08WM3F85K?ref=a_library_t_c5_libItem_&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=EZVGAWPQKM9ET3CPHQ44">
<span class="bc-text
    bc-size-headline3">Der Sternschnuppen-Regen: Paw Patrol 128-130</span>
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
    bc-color-base" tabindex="0" href="/search?searchAuthor=N.N.&amp;ref=a_library_t_c5_libItem_author_7&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=EZVGAWPQKM9ET3CPHQ44">
<span class="bc-text
    bc-size-callout">N.N.</span>
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
    bc-color-base" tabindex="0" href="/search?searchNarrator=Tobias+Diakow&amp;ref=a_library_t_c5_libItem_narrator_7&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=EZVGAWPQKM9ET3CPHQ44">
<span class="bc-text
    bc-size-callout">Tobias Diakow</span>
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
    bc-color-base" tabindex="0" href="/series/Paw-Patrol-Hoerbuecher/B07R53Y891?ref=a_library_t_c5_libItem_series_1&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=EZVGAWPQKM9ET3CPHQ44">Paw Patrol</a>, Titel 128-130
                            </span>
</li>
<li class="bc-list-item
	rateAndReviewLabel 
    bc-spacing-s0_5">
<div class="bc-box
			bc-box-padding-none
			adbl-prod-rate-review-bar-group
    bc-spacing-top-none">
<input type="hidden" name="asinForRatingStars" value="B08WM3F85K" class="asin-for-rating-stars">
<input type="hidden" name="ratingsCsrfToken" value="gqUIOjVBnO8IDKA2XbrwOe7yS0objAthv18dW0AAAAABAAAAAGCJdDZyYXcAAAAAFVfwRGgNifE9xfqJS///" class="ratings-csrf-token">
            <div class="bc-trigger
bc-trigger-tooltip">
  <div class="bc-rating-stars adbl-prod-rate-review-bar adbl-prod-rate-review-bar-overall" data-star-count="5" role="radiogroup" tabindex="0" asin="B08WM3F85K">
      <h3 class="bc-pub-offscreen">interactive rating stars</h3>
      <span class="bc-rating-star" data-index="1" data-text="1 Stern" role="radio" aria-checked="false" aria-label="1 Stern" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-star-fill-s2 bc-icon-fill-progress">
</i>
      </span>
      <span class="bc-rating-star" data-index="2" data-text="2 Sterne" role="radio" aria-checked="false" aria-label="2 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-star-fill-s2 bc-icon-fill-progress">
</i>
      </span>
      <span class="bc-rating-star" data-index="3" data-text="3 Sterne" role="radio" aria-checked="false" aria-label="3 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-star-fill-s2 bc-icon-fill-progress">
</i>
      </span>
      <span class="bc-rating-star" data-index="4" data-text="4 Sterne" role="radio" aria-checked="false" aria-label="4 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-star-fill-s2 bc-icon-fill-progress">
</i>
      </span>
      <span class="bc-rating-star" data-index="5" data-text="5 Sterne" role="radio" aria-checked="true" aria-label="5 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-star-fill-s2 bc-icon-fill-progress">
</i>
      </span>
  </div>
  <div class="bc-tooltip bc-tooltip-left bc-hidden  bc-palette-inverse bc-rating-stars-tooltip" role="tooltip">
      <div class="bc-tooltip-inner bc-color-background-inverse bc-color-background-base">
          <div class="bc-tooltip-message bc-color-base">
          </div>
      </div>
  </div>
  </div>
    &nbsp;
                <a class="bc-link
    bc-color-base" tabindex="0" href="/write-review?rdpath=%2Fpd%2FB08WM3F85K&amp;asin=B08WM3F85K&amp;source=lib&amp;page=1&amp;ref=a_library_t_c5_review&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=EZVGAWPQKM9ET3CPHQ44">Rezension schreiben</a>
</div>
</li>
<li class="bc-list-item
	summaryLabel 
    bc-spacing-s0_5 
    bc-spacing-top-s1">
<span class="bc-text
    merchandisingSummary 
    bc-size-body 
    bc-color-secondary"><p>PAW Patrol: Das sind Chase, Marshall, Rocky, Zuma, Rubble und Skye. Die sechs heldenhaften Hunde werden von dem Technikliebhaber Ryder...</p></span>
</li>
<li class="bc-list-item">
<input type="hidden" name="isFinished" value="true">
<div class="bc-box
			bc-box-padding-none
			adbl-library-item-button-row
    bc-spacing-top-s1" data-asin="B08WM3F85K" data-collection-id="">
    <span id="add-to-favorites-button-B08WM3F85K" class="bc-button
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
    <span id="remove-from-favorites-button-B08WM3F85K" class="bc-button
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
<input type="hidden" name="collectionIds-B08WM3F85K" value="[f891ff8e-c309-4d0c-b140-cf942ed8e4a8]" id="collectionIds-B08WM3F85K">
            <!-- These input fields are needed to localize success and failure toasts for adding to and removing from favorites-->
<input type="hidden" name="addToFavoritesSuccessMessage" value="Erfolgreich zu Favoriten hinzugefügt">
<input type="hidden" name="addToFavoritesFailureMessage" value="Fehler beim Hinzufügen zu Favoriten">
<input type="hidden" name="removeFromFavoritesSuccessMessage" value="Erfolgreich von Favoritenentfernt">
<input type="hidden" name="removeFromFavoritesFailureMessage" value="Fehler beim Entfernen von Favoriten">
            <!-- These input fields are needed to localize success and failure toasts for removing from a collection-->
<input type="hidden" name="removeFromCollectionSuccessMessage" value="Erfolgreich von Sammlungentfernt">
<input type="hidden" name="removeFromCollectionFailureMessage" value="Fehler beim Entfernen von Sammlung">
    <span id="mark-as-unfinished-button-B08WM3F85K" class="bc-button
  bc-button-simple
  mark-as-unfinished-button  
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
    <span id="mark-as-finished-button-B08WM3F85K" class="bc-button
  bc-button-simple
  mark-as-finished-button bc-pub-hidden 
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
    adbl-library-item-select-checkbox adbl-library-item-select-checkbox-B08WM3F85K">
	<label class="bc-label-wrapper ">
    <div class="bc-checkbox-wrapper">
        <input type="checkbox" autocomplete="off" data-asin="B08WM3F85K" data-img-url="https://m.media-amazon.com/images/I/51Upaz80RaL._SL500_.jpg" data-img-alt-text="Der Sternschnuppen-Regen Titelbild">
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
    bc-color-secondary" id="time-remaining-finished-B08WM3F85K">
    Beendet
</span>
</div>
<div id="" class="bc-row-responsive
    adbl-library-item 
    bc-spacing-top-s2" style="">
        <input type="hidden" name="asin" value="B08WM3F85K">
        <input type="hidden" name="contentType" value="Performance">
        <input type="hidden" name="contentDeliveryType" value="SinglePartBook">
        <input type="hidden" name="asinIndex" value="7">
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
      <a class="bc-button-text" href="https://cds.audible.de/download?asin=B08WM3F85K&amp;cust_id=PPPNCK8BrttjnFHfyA6jWicimniObhXhyyocTa8wQQKWguUe5-6Ujpt9xVB8&amp;codec=LC_128_44100_Stereo&amp;source=Audible&amp;type=AUDI" tabindex="0">
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
                null,
            ],
            '[Charly und der Wunderwombat Waldemar] 1 Staffel' => [
                '[Charly und der Wunderwombat Waldemar] 1 Staffel',
                '<div id="adbl-library-content-row-B08BBXSFLY" class="adbl-library-content-row">
<div id="" class="bc-row-responsive
    bc-spacing-top-s2" style="">
<div class="bc-col-responsive
    bc-spacing-top-none 
    bc-col-2">
<div id="" class="bc-row-responsive" style="">
                    <a class="bc-link
    bc-color-link" tabindex="0" href="/pd/Charly-und-der-Wunderwombat-Waldemar-Hoerbuch/B08BBXSFLY?ref=a_library_t_c5_libItem_&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=PA9H4W785GCBAX8EZTAH">
<img id="" class="bc-pub-block
    bc-image-inset-border js-only-element" src="https://m.media-amazon.com/images/I/51oRNuTX82L._SL500_.jpg" alt="Charly und der Wunderwombat Waldemar Titelbild">
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
    bc-color-base" tabindex="0" href="/pd/Charly-und-der-Wunderwombat-Waldemar-Hoerbuch/B08BBXSFLY?ref=a_library_t_c5_libItem_&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=PA9H4W785GCBAX8EZTAH">
<span class="bc-text
    bc-size-headline3">Charly und der Wunderwombat Waldemar: Staffel 1</span>
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
    bc-color-base" tabindex="0" href="/author/Sophie-Seeberg/B00J20BOFY?ref=a_library_t_c5_libItem_author_8&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=PA9H4W785GCBAX8EZTAH">
<span class="bc-text
    bc-size-callout">Sophie Seeberg</span>
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
    bc-color-base" tabindex="0" href="/search?searchNarrator=Angelika+Bender&amp;ref=a_library_t_c5_libItem_narrator_8&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=PA9H4W785GCBAX8EZTAH">
<span class="bc-text
    bc-size-callout">Angelika Bender</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=Paulina+R%C3%BCmmelein&amp;ref=a_library_t_c5_libItem_narrator_8&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=PA9H4W785GCBAX8EZTAH">
<span class="bc-text
    bc-size-callout">Paulina Rümmelein</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=Caroline+Ebner&amp;ref=a_library_t_c5_libItem_narrator_8&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=PA9H4W785GCBAX8EZTAH">
<span class="bc-text
    bc-size-callout">Caroline Ebner</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=Laura+Jenni&amp;ref=a_library_t_c5_libItem_narrator_8&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=PA9H4W785GCBAX8EZTAH">
<span class="bc-text
    bc-size-callout">Laura Jenni</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=Maresa+Sedlmeir&amp;ref=a_library_t_c5_libItem_narrator_8&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=PA9H4W785GCBAX8EZTAH">
<span class="bc-text
    bc-size-callout">Maresa Sedlmeir</span>
                                        </a>,
                                    <a class="bc-link
    bc-color-base" tabindex="0" href="/search?searchNarrator=Kai+Taschner&amp;ref=a_library_t_c5_libItem_narrator_8&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=PA9H4W785GCBAX8EZTAH">
<span class="bc-text
    bc-size-callout">Kai Taschner</span>
                                        </a>
                            </span>
</li>
<li class="bc-list-item
	rateAndReviewLabel 
    bc-spacing-s0_5">
<div class="bc-box
			bc-box-padding-none
			adbl-prod-rate-review-bar-group
    bc-spacing-top-none">
<input type="hidden" name="asinForRatingStars" value="B08BBXSFLY" class="asin-for-rating-stars">
<input type="hidden" name="ratingsCsrfToken" value="gp1/UvPpFjBiIw2YFGnmAFfKfiW5nY3FxjDjVbwAAAABAAAAAGCJ0ipyYXcAAAAAFVfwRGgNifE9xfqJS///" class="ratings-csrf-token">
            <div class="bc-trigger
bc-trigger-tooltip">
  <div class="bc-rating-stars adbl-prod-rate-review-bar adbl-prod-rate-review-bar-overall" data-star-count="5" role="radiogroup" tabindex="0" asin="B08BBXSFLY" aria-describedby="tooltip1619644991977">
      <h3 class="bc-pub-offscreen">interactive rating stars</h3>
      <span class="bc-rating-star" data-index="1" data-text="1 Stern" role="radio" aria-checked="false" aria-label="1 Stern" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-star-fill-s2 bc-icon-fill-progress">
</i>
      </span>
      <span class="bc-rating-star" data-index="2" data-text="2 Sterne" role="radio" aria-checked="false" aria-label="2 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-star-fill-s2 bc-icon-fill-progress">
</i>
      </span>
      <span class="bc-rating-star" data-index="3" data-text="3 Sterne" role="radio" aria-checked="false" aria-label="3 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-star-fill-s2 bc-icon-fill-progress">
</i>
      </span>
      <span class="bc-rating-star" data-index="4" data-text="4 Sterne" role="radio" aria-checked="false" aria-label="4 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-star-fill-s2 bc-icon-fill-progress">
</i>
      </span>
      <span class="bc-rating-star" data-index="5" data-text="5 Sterne" role="radio" aria-checked="true" aria-label="5 Sterne" tabindex="0">
<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
<i aria-hidden="true" class="bc-icon bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-star-fill-s2 bc-icon-fill-progress">
</i>
      </span>
  </div>
  <div class="bc-tooltip bc-tooltip-left bc-palette-inverse bc-rating-stars-tooltip bc-hidden" role="tooltip" id="tooltip1619644991977" style="top: -7px;">
      <div class="bc-tooltip-inner bc-color-background-inverse bc-color-background-base">
          <div class="bc-tooltip-message bc-color-base">1 Stern</div>
      </div>
  </div>
  </div>
    &nbsp;
                <a class="bc-link
    bc-color-base" tabindex="0" href="/write-review?rdpath=%2Fpd%2FB08BBXSFLY&amp;asin=B08BBXSFLY&amp;source=lib&amp;page=2&amp;ref=a_library_t_c5_review&amp;pf_rd_p=5a58e3a9-ade2-4fed-b6e2-d91a60ca8ff4&amp;pf_rd_r=PA9H4W785GCBAX8EZTAH">Rezension schreiben</a>
</div>
</li>
<li class="bc-list-item
	summaryLabel 
    bc-spacing-s0_5 
    bc-spacing-top-s1">
<span class="bc-text
    merchandisingSummary 
    bc-size-body 
    bc-color-secondary"><p>Eine Audible Original Hörspielserie über magische Niesanfälle, kleine Schwestern, irre Zeitreisen, schlaue Hunde, latente Lachanfälle...</p></span>
</li>
<li class="bc-list-item">
<input type="hidden" name="isFinished" value="false">
<div class="bc-box
			bc-box-padding-none
			adbl-library-item-button-row
    bc-spacing-top-s1" data-asin="B08BBXSFLY" data-collection-id="">
    <span id="add-to-favorites-button-B08BBXSFLY" class="bc-button
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
    <span id="remove-from-favorites-button-B08BBXSFLY" class="bc-button
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
<input type="hidden" name="collectionIds-B08BBXSFLY" value="" id="collectionIds-B08BBXSFLY">
            <!-- These input fields are needed to localize success and failure toasts for adding to and removing from favorites-->
<input type="hidden" name="addToFavoritesSuccessMessage" value="Erfolgreich zu Favoriten hinzugefügt">
<input type="hidden" name="addToFavoritesFailureMessage" value="Fehler beim Hinzufügen zu Favoriten">
<input type="hidden" name="removeFromFavoritesSuccessMessage" value="Erfolgreich von Favoritenentfernt">
<input type="hidden" name="removeFromFavoritesFailureMessage" value="Fehler beim Entfernen von Favoriten">
            <!-- These input fields are needed to localize success and failure toasts for removing from a collection-->
<input type="hidden" name="removeFromCollectionSuccessMessage" value="Erfolgreich von Sammlungentfernt">
<input type="hidden" name="removeFromCollectionFailureMessage" value="Fehler beim Entfernen von Sammlung">
    <span id="mark-as-unfinished-button-B08BBXSFLY" class="bc-button
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
    <span id="mark-as-finished-button-B08BBXSFLY" class="bc-button
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
    adbl-library-item-select-checkbox adbl-library-item-select-checkbox-B08BBXSFLY">
	<label class="bc-label-wrapper ">
    <div class="bc-checkbox-wrapper">
        <input type="checkbox" autocomplete="off" data-asin="B08BBXSFLY" data-img-url="https://m.media-amazon.com/images/I/51oRNuTX82L._SL500_.jpg" data-img-alt-text="Charly und der Wunderwombat Waldemar Titelbild">
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
    bc-color-secondary" id="time-remaining-finished-B08BBXSFLY">
    Beendet
</span>
<div id="time-remaining-display-B08BBXSFLY" class="bc-row-responsive" style="">
<div class="bc-col-responsive
    no-padding-right 
    bc-spacing-top-s1 
    bc-text-right 
    bc-col-5">
<div class="bc-meter bc-color-background-tertiary" style="height:5px">
    <div role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="59" class="bc-meter-bar bc-color-background-progress" style="width:59%"></div>
</div>
</div>
<div class="bc-col-responsive
    small-padding-left 
    bc-text-left 
    bc-col-7">
<span class="bc-text
    bc-color-secondary">2 Std. 43 Min. verbleibend</span>
</div>
</div>
</div>
<div id="" class="bc-row-responsive
    adbl-library-item 
    bc-spacing-top-s2" style="">
        <input type="hidden" name="asin" value="B08BBXSFLY">
        <input type="hidden" name="contentType" value="Performance">
        <input type="hidden" name="contentDeliveryType" value="SinglePartBook">
        <input type="hidden" name="asinIndex" value="8">
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
      <a class="bc-button-text" href="https://cds.audible.de/download?asin=B08BBXSFLY&amp;cust_id=rsTVstmbuI1seRq24MBirffGpKRHtAkp0ASObMDTlsMVSWf7iiJFGXb_TwOt&amp;codec=LC_128_44100_Stereo&amp;source=Audible&amp;type=AUDI" tabindex="0">
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
                null,
            ],
            '[SchauerGeschichte: Ängste vergangener Zeiten. Staffel 2 (Original Podcast)] Flg. 12 - (Der Horror der) Hexenverfolgung: Salem/Neuengland 1692' => [
                '[SchauerGeschichte: Ängste vergangener Zeiten. Staffel 2 (Original Podcast)] Flg. 12 - (Der Horror der) Hexenverfolgung - Salem/Neuengland 1692',
                '<div id="adbl-library-content-row-B09BYJ7JCH" class="adbl-library-content-row">










 








<div id="" class="bc-row-responsive
    
    bc-spacing-top-s2" style="">
    
    
    












































 



<div class="bc-col-responsive
    
    
    bc-spacing-top-none 
    

    bc-col-2">
    
        









 








<div id="" class="bc-row-responsive" style="">
    
            
                
                    





































  
  




















    
    
        
        
        <img id="" class="bc-pub-block
    
    bc-image-inset-border js-only-element" src="https://m.media-amazon.com/images/I/5184rdB-wiL._SL500_.jpg" alt="SchauerGeschichte: Ängste vergangener Zeiten. Staffel 2 (Original Podcast) Titelbild">
    


                
                
            
        
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
    
                        
                        
                            
                                
 

  
<span class="bc-text
    
    bc-size-headline3">SchauerGeschichte: Ängste vergangener Zeiten. Staffel 2 (Original Podcast)</span>
                            
                            
                        
                    
</li>

                    



















<li class="bc-list-item">
    
                        
                    
</li>

                    
                    
                        



















<li class="bc-list-item
	authorLabel 
    bc-spacing-s0_5 
    bc-spacing-top-s0_5">
    
                            
 

  
<span class="bc-text
    
    
    
    bc-color-secondary">
                                Von:
                                
                                    
                                    <a class="bc-link
    
    
    bc-color-base" tabindex="0" href="/search?searchAuthor=SchauerGeschichte%3A+%C3%84ngste+vergangener+Zeiten&amp;ref=a_library_t_c5_libItem_author_11&amp;pf_rd_p=86298143-6994-4968-8277-2e2391d86bbd&amp;pf_rd_r=8V1HQASF95CZNP750KR5">
                                            
 

  
<span class="bc-text
    
    bc-size-callout">SchauerGeschichte: Ängste vergangener Zeiten</span>
                                        </a>,
                                
                                    
                                    <a class="bc-link
    
    
    bc-color-base" tabindex="0" href="/search?searchAuthor=Francis+Nenik&amp;ref=a_library_t_c5_libItem_author_11&amp;pf_rd_p=86298143-6994-4968-8277-2e2391d86bbd&amp;pf_rd_r=8V1HQASF95CZNP750KR5">
                                            
 

  
<span class="bc-text
    
    bc-size-callout">Francis Nenik</span>
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
    
    
    bc-color-base" tabindex="0" href="/search?searchNarrator=Nic+Romm&amp;ref=a_library_t_c5_libItem_narrator_11&amp;pf_rd_p=86298143-6994-4968-8277-2e2391d86bbd&amp;pf_rd_r=8V1HQASF95CZNP750KR5">
                                            
 

  
<span class="bc-text
    
    bc-size-callout">Nic Romm</span>
                                        </a>
                                
                            </span>
                        
</li>

                    
                    
                    
                    
                    
                    
                    



















<li class="bc-list-item
	summaryLabel 
    bc-spacing-s0_5 
    bc-spacing-top-s1">
    
                        
 

  
<span class="bc-text
    merchandisingSummary 
    bc-size-body 
    
    bc-color-secondary"><p>Die Geschichte der Menschheit ist voller dunkler Legenden. "SchauerGeschichte" nimmt dich mit auf eine ungewöhnliche Reise in die Vergangenheit...</p></span>
                    
</li>

                    



















<li class="bc-list-item">
    
                        
                        













<input type="hidden" name="isFinished" value="false">























 
















<div class="bc-box
		
			bc-box-padding-none
			
			adbl-library-item-button-row
    
    
    bc-spacing-top-s1" data-asin="B09BYJ7JCH" data-collection-id="">
	
			
      
        
    

      
  
</div>

                    
</li>

                </ul>
</span>

            
</div>

            
                
                    












































 



<div class="bc-col-responsive
    
    
    
    

    bc-col-1 
    
    
    bc-col-offset-2">
    
                        









 








<div id="" class="bc-row-responsive
    adbl-episodes-link" style="">
    
                            <input type="hidden" name="asin" value="B09BYJ7JCH">
                            <input type="hidden" name="contentType" value="Show">
                            <input type="hidden" name="contentDeliveryType" value="Periodical">
                            <input type="hidden" name="asinIndex" value="11">
                            
                                
                                
                                    
                                    
                                
                            
                            
                            <a class="bc-link
    
    
    bc-color-base 
    
    
    
    
    
    bc-text-normal" tabindex="0" aria-label="SchauerGeschichte: Ängste vergangener Zeiten. Staffel 2 (Original Podcast)Alle Folgen zeigen" href="/library/episodes?parentAsin=B09BYJ7JCH&amp;parentTitle=SchauerGeschichte%3a+%c3%84ngste+vergangener+Zeiten.+Staffel+2+(Original+Podcast)&amp;ref=a_library_t_c5_view_all_episodes_11&amp;pf_rd_p=86298143-6994-4968-8277-2e2391d86bbd&amp;pf_rd_r=8V1HQASF95CZNP750KR5">
                                





















 



	














<div style="height: 171px;" class="bc-box
		
			bc-box-padding-base
			
			chevron-container">
	
			
      
        
                                    












	
	
	
	








<i style="vertical-align: center" aria-hidden="true" class="bc-icon
	bc-icon-fill-base
	bc-icon-chevron-right-s4
	
	bc-icon-chevron-right 
	bc-icon-size-large 
	bc-color-base">
</i>
                                
      
  
</div>

                            </a>
                        
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
                    '<div id="adbl-library-content-row-B09BZ15JWR" class="adbl-library-content-row">










 








<div id="" class="bc-row-responsive
    
    bc-spacing-top-s2" style="">
    
    
    












































 



<div class="bc-col-responsive
    
    
    bc-spacing-top-none 
    

    bc-col-2">
    
        









 








<div id="" class="bc-row-responsive" style="">
    
            
                
                
                    <a class="bc-link
    
    
    bc-color-link" tabindex="0" href="/pd/Flg-12-Der-Horror-der-Hexenverfolgung-Salem-Neuengland-1692-Hoerbuch/B09BZ15JWR?ref=a_library_e_c5_libItem_&amp;pf_rd_p=86298143-6994-4968-8277-2e2391d86bbd&amp;pf_rd_r=9AEPHDVRHWQQYNSBQR45">
                        





































  
  




















    
    
        
        
        <img id="" class="bc-pub-block
    
    bc-image-inset-border js-only-element" src="https://m.media-amazon.com/images/I/5184rdB-wiL._SL500_.jpg" alt="Flg. 12 - (Der Horror der) Hexenverfolgung: Salem/Neuengland 1692 Titelbild">
    


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
    
    
    bc-color-base" tabindex="0" href="/pd/Flg-12-Der-Horror-der-Hexenverfolgung-Salem-Neuengland-1692-Hoerbuch/B09BZ15JWR?ref=a_library_e_c5_libItem_&amp;pf_rd_p=86298143-6994-4968-8277-2e2391d86bbd&amp;pf_rd_r=9AEPHDVRHWQQYNSBQR45">
                                    
 

  
<span class="bc-text
    
    bc-size-headline3">Flg. 12 - (Der Horror der) Hexenverfolgung: Salem/Neuengland 1692</span>
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
                                Von:
                                
                                    
                                    <a class="bc-link
    
    
    bc-color-base" tabindex="0" href="/search?searchAuthor=SchauerGeschichte%3A+%C3%84ngste+vergangener+Zeiten&amp;ref=a_library_e_c5_libItem_author_1&amp;pf_rd_p=86298143-6994-4968-8277-2e2391d86bbd&amp;pf_rd_r=9AEPHDVRHWQQYNSBQR45">
                                            
 

  
<span class="bc-text
    
    bc-size-callout">SchauerGeschichte: Ängste vergangener Zeiten</span>
                                        </a>,
                                
                                    
                                    <a class="bc-link
    
    
    bc-color-base" tabindex="0" href="/search?searchAuthor=Francis+Nenik&amp;ref=a_library_e_c5_libItem_author_1&amp;pf_rd_p=86298143-6994-4968-8277-2e2391d86bbd&amp;pf_rd_r=9AEPHDVRHWQQYNSBQR45">
                                            
 

  
<span class="bc-text
    
    bc-size-callout">Francis Nenik</span>
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
    
    
    bc-color-base" tabindex="0" href="/search?searchNarrator=Nic+Romm&amp;ref=a_library_e_c5_libItem_narrator_1&amp;pf_rd_p=86298143-6994-4968-8277-2e2391d86bbd&amp;pf_rd_r=9AEPHDVRHWQQYNSBQR45">
                                            
 

  
<span class="bc-text
    
    bc-size-callout">Nic Romm</span>
                                        </a>
                                
                            </span>
                        
</li>

                    
                    
                    
                    
                    
                        



















<li class="bc-list-item
	rateAndReviewLabel 
    bc-spacing-s0_5">
    
                            





























































 
















<div class="bc-box
		
			bc-box-padding-none
			
			adbl-prod-rate-review-bar-group
    
    
    bc-spacing-top-none">
	
			
      
        
    
        
            








<input type="hidden" name="asinForRatingStars" value="B09BZ15JWR" class="asin-for-rating-stars">

            








<input type="hidden" name="ratingsCsrfToken" value="ggRE0mB2+CAwx8Y41TzIi0Nv+eIm0OezNx9t3dAAAAABAAAAAGMPEmpyYXcAAAAAFVfwRGgNifE9xfqJS///" class="ratings-csrf-token">

            <div class="bc-trigger


bc-trigger-tooltip">
  
  
  <div class="bc-rating-stars adbl-prod-rate-review-bar adbl-prod-rate-review-bar-overall" data-star-count="0" role="radiogroup" tabindex="0" asin="B09BZ15JWR">
      <h3 class="bc-pub-offscreen">interactive rating stars</h3>
      <span class="bc-rating-star" data-index="1" data-text="1 Stern" role="radio" aria-checked="false" aria-label="1 Stern" tabindex="0">
          












	
	
	
	








<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
          












	
	
	
	








<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="2" data-text="2 Sterne" role="radio" aria-checked="false" aria-label="2 Sterne" tabindex="0">
          












	
	
	
	








<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
          












	
	
	
	








<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="3" data-text="3 Sterne" role="radio" aria-checked="false" aria-label="3 Sterne" tabindex="0">
          












	
	
	
	








<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
          












	
	
	
	








<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="4" data-text="4 Sterne" role="radio" aria-checked="false" aria-label="4 Sterne" tabindex="0">
          












	
	
	
	








<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
          












	
	
	
	








<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      <span class="bc-rating-star" data-index="5" data-text="5 Sterne" role="radio" aria-checked="false" aria-label="5 Sterne" tabindex="0">
          












	
	
	
	








<i aria-hidden="true" class="bc-icon bc-icon-fill-active bc-icon-star-empty-s2 star-border bc-pub-hidden bc-icon-star-empty bc-icon-size-small bc-color-active">
</i>
          












	
	
	
	








<i aria-hidden="true" class="bc-icon bc-icon-star-empty-s2 bc-icon-star-empty bc-icon-size-small bc-color-active bc-icon-fill-active">
</i>
      </span>
      
  </div>
  

  <div class="bc-tooltip bc-tooltip-left bc-hidden  bc-palette-inverse bc-rating-stars-tooltip" role="tooltip">
      <div class="bc-tooltip-inner bc-color-background-inverse bc-color-background-base">
          <div class="bc-tooltip-message bc-color-base">
          
          </div>
      </div>
  </div>

</div>
        
        
    
    &nbsp;
    
    
        
            
                <a class="bc-link
    
    
    bc-color-base" tabindex="0" href="/write-review?rdpath=%2Fpd%2FB09BZ15JWR&amp;asin=B09BZ15JWR&amp;source=lib&amp;page=1&amp;ref=a_library_e_c5_review&amp;pf_rd_p=86298143-6994-4968-8277-2e2391d86bbd&amp;pf_rd_r=9AEPHDVRHWQQYNSBQR45">Rezension schreiben</a>
            
            
        
    

      
  
</div>

                        
</li>

                    
                    
                    



















<li class="bc-list-item
	summaryLabel 
    bc-spacing-s0_5 
    bc-spacing-top-s1">
    
                        
 

  
<span class="bc-text
    merchandisingSummary 
    bc-size-body 
    
    bc-color-secondary"><p>Ihr größtes Ausmaß erreichte die Hexenverfolgung im Europa des 16. und 17. Jahrhunderts. Auch die englischen Kolonien im Nordosten der USA...</p></span>
                    
</li>

                    



















<li class="bc-list-item">
    
                        
                        













<input type="hidden" name="isFinished" value="true">























 
















<div class="bc-box
		
			bc-box-padding-none
			
			adbl-library-item-button-row
    
    
    bc-spacing-top-s1" data-asin="B09BZ15JWR" data-collection-id="">
	
			
      
        
    
        
            



















 
 


  
  
  
  
  
  



  
    
  
  















  

  

  

  
    
    <span id="add-to-favorites-button-B09BZ15JWR" class="bc-button
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
  


            



















 
 


  
  
  
  
  
  



  
    
  
  















  

  

  

  
    
    <span id="remove-from-favorites-button-B09BZ15JWR" class="bc-button
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
  


            

            








<input type="hidden" name="collectionIds-B09BZ15JWR" value="" id="collectionIds-B09BZ15JWR">


            <!-- These input fields are needed to localize success and failure toasts for adding to and removing from favorites-->
            
            
            








<input type="hidden" name="addToFavoritesSuccessMessage" value="Erfolgreich zu Favoriten hinzugefügt">

            
            








<input type="hidden" name="addToFavoritesFailureMessage" value="Fehler beim Hinzufügen zu Favoriten">

            
            








<input type="hidden" name="removeFromFavoritesSuccessMessage" value="Erfolgreich von Favoritenentfernt">

            
            








<input type="hidden" name="removeFromFavoritesFailureMessage" value="Fehler beim Entfernen von Favoriten">


            <!-- These input fields are needed to localize success and failure toasts for removing from a collection-->
            
            
            








<input type="hidden" name="removeFromCollectionSuccessMessage" value="Erfolgreich von Sammlungentfernt">

            
            








<input type="hidden" name="removeFromCollectionFailureMessage" value="Fehler beim Entfernen von Sammlung">

        

        
        
            



















 
 


  
  
  
  
  
  



  
    
  
  















  

  

  

  
    
    <span id="mark-as-unfinished-button-B09BZ15JWR" class="bc-button
  bc-button-simple
  mark-as-unfinished-button  
  
  
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
  


            



















 
 


  
  
  
  
  
  



  
    
  
  















  

  

  

  
    
    <span id="mark-as-finished-button-B09BZ15JWR" class="bc-button
  bc-button-simple
  mark-as-finished-button bc-pub-hidden 
  
  
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
  


            <input type="hidden" class="markAsFinished-csrf-token" name="markAsFinishedToken" value="gnV1sQXSv/vuCQKe2qKPFCLZEWaL6ujMnj7xSDgAAAABAAAAAGMPEmpyYXcAAAAAFVfwRGgNifE9xfqJS///">
        
    

      
  
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
    adbl-library-item-select-checkbox adbl-library-item-select-checkbox-B09BZ15JWR">
	<label class="bc-label-wrapper ">
    <div class="bc-checkbox-wrapper">
        <input type="checkbox" autocomplete="off" data-asin="B09BZ15JWR" data-img-url="https://m.media-amazon.com/images/I/5184rdB-wiL._SL500_.jpg" data-img-alt-text="Flg. 12 - (Der Horror der) Hexenverfolgung: Salem/Neuengland 1692 Titelbild">
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
    
    
    
    bc-color-secondary" id="time-remaining-finished-B09BZ15JWR">
    Beendet
</span>

        
</div>

    
    
    









 








<div id="" class="bc-row-responsive
    adbl-library-item 
    bc-spacing-top-s2" style="">
    
        <input type="hidden" name="asin" value="B09BZ15JWR">
        <input type="hidden" name="contentType" value="Show">
        <input type="hidden" name="contentDeliveryType" value="SinglePartIssue">
        <input type="hidden" name="asinIndex" value="1">
        <input type="hidden" name="isPrerelease" value="false">
        <input type="hidden" name="deepLinkUrl" value="">
        



















 
 


  
  
  
  
  
  



  
    
  
  















  

  

  

  
    
    <span class="bc-button
  bc-button-primary
  adbl-library-listen-now-button 
  
  
  bc-button-small">
      <button class="bc-button-text" type="button" tabindex="0">
        <span class="bc-text
    bc-button-text-inner 
    bc-size-action-small">
    
    
            
 

  
<span class="bc-text
    
    bc-size-callout">Abspielen</span>
        
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
      <a class="bc-button-text" href="/library/download?asin=B09BZ15JWR&amp;codec=AAX_44_128" tabindex="0" role="button">
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
                Du hast diesen Titel heruntergeladen
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
