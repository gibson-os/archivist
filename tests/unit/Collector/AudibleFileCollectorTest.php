<?php
declare(strict_types=1);

namespace GibsonOS\Test\Unit\Archivist\Collector;

use Codeception\Test\Unit;
use DateTime;
use GibsonOS\Core\Service\DateTimeService;
use GibsonOS\Module\Archivist\Collector\AudibleFileCollector;
use GibsonOS\Module\Archivist\Dto\File;
use GibsonOS\Module\Archivist\Model\Account;
use GibsonOS\Test\Unit\Core\ModelManagerTrait;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\NullLogger;

class AudibleFileCollectorTest extends Unit
{
    use ProphecyTrait;
    use ModelManagerTrait;

    private AudibleFileCollector $audibleFileCollector;

    private DateTimeService|ObjectProphecy $dateTimeService;

    private DateTime $dateTime;

    protected function _before()
    {
        $this->loadModelManager();
        $this->dateTimeService = $this->prophesize(DateTimeService::class);
        $this->dateTime = new DateTime();

        $this->audibleFileCollector = new AudibleFileCollector(
            new NullLogger(),
            $this->dateTimeService->reveal(),
        );
    }

    /**
     * @dataProvider getData
     */
    public function testGetFilesFromPage(string $page, array $files): void
    {
        $this->dateTimeService->get()
            ->willReturn($this->dateTime)
        ;
        $page = file_get_contents(sprintf('tests/_data/audible/library/%s', $page));
        $account = new Account($this->modelWrapper->reveal());
        $files = array_map(
            fn (array $file): File => new File($file['name'], $file['path'], $this->dateTime, $account),
            $files,
        );
        $files[] = null;

        $this->assertEquals($files, iterator_to_array($this->audibleFileCollector->getFilesFromPage($account, $page)));
    }

    public function getData(): array
    {
        return [
            'page 1' => [
                'page1.html',
                [
                    [
                        'name' => '[Caldera] 2 Die Rückkehr der Schattenwandler',
                        'path' => 'https://www.audible.de/library/download?asin=3844920560&codec=AAX_44_128',
                    ], [
                        'name' => '[Caldera] 1 Die Wächter des Dschungels',
                        'path' => 'https://www.audible.de/library/download?asin=B07FJMRR99&codec=AAX_44_128',
                    ], [
                        'name' => '[Shining-Reihe] 1 Shining',
                        'path' => 'https://www.audible.de/library/download?asin=B0081RJN9I&codec=AAX_44_128',
                    ], [
                        'name' => 'Nano - Jede Sekunde zählt',
                        'path' => 'https://www.audible.de/library/download?asin=B0BMVTM1LL&codec=AAX_44_128',
                    ],
                ],
            ],
            'page 2' => [
                'page2.html',
                [
                    [
                        'name' => 'Devolution',
                        'path' => 'https://www.audible.de/library/download?asin=383714934X&codec=AAX_44_128',
                    ], [
                        'name' => '[Ghostsitter] 18 Ein missglückter Antrag',
                        'path' => 'https://www.audible.de/library/download?asin=B0CPYB7ZQH&codec=AAX_44_128',
                    ], [
                        'name' => '[Das Pummeleinhorn] 6 Das Pummeleinhorn',
                        'path' => 'https://www.audible.de/library/download?asin=B0CLVMSW5W&codec=AAX_44_128',
                    ], [
                        'name' => 'Urwelten - Eine Reise durch die ausgestorbenen Ökosysteme der Erdgeschichte',
                        'path' => 'https://www.audible.de/library/download?asin=B0CBCKX4X9&codec=AAX_44_128',
                    ], [
                        'name' => '[Ghostsitter] 17 Drei Hexen',
                        'path' => 'https://www.audible.de/library/download?asin=B0CLVN847W&codec=AAX_44_128',
                    ], [
                        'name' => '[Animox] 2 Animox',
                        'path' => 'https://www.audible.de/library/download?asin=B071XVF6GR&codec=AAX_44_128',
                    ],
                ],
            ],
            'page 3' => [
                'page3.html',
                [
                    [
                        'name' => '[Game of Thrones - Das Lied von Eis und Feuer] 8 Game of Thrones - Das Lied von Eis und Feuer',
                        'path' => 'https://www.audible.de/library/download?asin=B004V0BKPY&codec=AAX_44_128',
                    ], [
                        'name' => '[Glory or Grave] 2 Das Gold der Krähen',
                        'path' => 'https://www.audible.de/library/download?asin=3958624073&codec=AAX_44_128',
                    ], [
                        'name' => '[Glory or Grave] 1 Das Lied der Krähen',
                        'path' => 'https://www.audible.de/library/download?asin=B075WZ4JKN&codec=AAX_44_128',
                    ], [
                        'name' => 'Homo Deus - Eine Geschichte von Morgen',
                        'path' => 'https://www.audible.de/library/download?asin=B06X3RQW9P&codec=AAX_44_128',
                    ], [
                        'name' => 'Die unerhörte Reise der Familie Lawson',
                        'path' => 'https://www.audible.de/library/download?asin=B0C4B51P8H&codec=AAX_44_128',
                    ], [
                        'name' => '21 Lektionen für das 21. Jahrhundert',
                        'path' => 'https://www.audible.de/library/download?asin=3844532870&codec=AAX_44_128',
                    ], [
                        'name' => '[Ghostsitter] 15 Das Geheimnis von Bad Klosterhof',
                        'path' => 'https://www.audible.de/library/download?asin=B0C6XWLJ5T&codec=AAX_44_128',
                    ], [
                        'name' => 'QualityLand 2.0 - Kikis Geheimnis',
                        'path' => 'https://www.audible.de/library/download?asin=384492602X&codec=AAX_44_128',
                    ],
                ],
            ],
            'page 4' => [
                'page4.html',
                [
                    [
                        'name' => '[Die phantastischen Fälle des Rufus T. Feuerflieg] 17 Das tiefe Tiefgeschoss',
                        'path' => 'https://www.audible.de/library/download?asin=B0BYJR6W77&codec=AAX_44_128',
                    ], [
                        'name' => '[DOORS - Staffel 2] 1 DOORS - Wächter',
                        'path' => 'https://www.audible.de/library/download?asin=B07YSR1RDL&codec=AAX_44_128',
                    ], [
                        'name' => '[DOORS] 1 ! - Blutfeld',
                        'path' => 'https://www.audible.de/library/download?asin=B07GPQ3W85&codec=AAX_44_128',
                    ], [
                        'name' => '[Die Drachenschule] 1 Tochter der Drachen',
                        'path' => 'https://www.audible.de/library/download?asin=B0BS6K2TRQ&codec=AAX_44_128',
                    ], [
                        'name' => '[DOORS - Staffel 2] 1 DOORS - Vorsehung',
                        'path' => 'https://www.audible.de/library/download?asin=B07YVBLL7G&codec=AAX_44_128',
                    ], [
                        'name' => '[Metro] 2 2034',
                        'path' => 'https://www.audible.de/library/download?asin=B01HO013QI&codec=AAX_44_128',
                    ], [
                        'name' => '[Das Pummeleinhorn] 5 Das Pummeleinhorn',
                        'path' => 'https://www.audible.de/library/download?asin=B0BNN8BH4B&codec=AAX_44_128',
                    ], [
                        'name' => '[Game of Thrones - Das Lied von Eis und Feuer] 7 Game of Thrones - Das Lied von Eis und Feuer',
                        'path' => 'https://www.audible.de/library/download?asin=B004V0LVZI&codec=AAX_44_128',
                    ],
                ],
            ],
            'page 5' => [
                'page5.html',
                [
                    [
                        'name' => '[Die phantastischen Fälle des Rufus T. Feuerflieg] 16 Der Boden ist Lava',
                        'path' => 'https://www.audible.de/library/download?asin=B0BM51V4F6&codec=AAX_44_128',
                    ], [
                        'name' => 'Ein gutes Omen - Der völlig andere Hexen-Roman',
                        'path' => 'https://www.audible.de/library/download?asin=B01N8Y3Q1C&codec=AAX_44_128',
                    ], [
                        'name' => '[Lord Schmetterhemd] 9 Showdown im Pueblo',
                        'path' => 'https://www.audible.de/library/download?asin=B09WQHVB55&codec=AAX_44_128',
                    ], [
                        'name' => '[Game of Thrones - Das Lied von Eis und Feuer] 6 Game of Thrones - Das Lied von Eis und Feuer',
                        'path' => 'https://www.audible.de/library/download?asin=B004V3FBLA&codec=AAX_44_128',
                    ], [
                        'name' => '[Die phantastischen Fälle des Rufus T. Feuerflieg] 15 Das Coaching',
                        'path' => 'https://www.audible.de/library/download?asin=B0BHTG5DL2&codec=AAX_44_128',
                    ],
                ],
            ],
            'page 6' => [
                'page6.html',
                [
                    [
                        'name' => '[Die phantastischen Fälle des Rufus T. Feuerflieg] 14 Die Hexe und der Dieb',
                        'path' => 'https://www.audible.de/library/download?asin=B0BDFTXKCT&codec=AAX_44_128',
                    ], [
                        'name' => '[Death Note] 7-12 Die Hörspielreihe',
                        'path' => 'https://www.audible.de/library/download?asin=3838794184&codec=AAX_44_128',
                    ], [
                        'name' => '[Ghostsitter] 14 Der Höllenritt',
                        'path' => 'https://www.audible.de/library/download?asin=B0BCKBJXL4&codec=AAX_44_128',
                    ], [
                        'name' => '[Die phantastischen Fälle des Rufus T. Feuerflieg] 13 Hoaxbusters',
                        'path' => 'https://www.audible.de/library/download?asin=B0BB3M82YX&codec=AAX_44_128',
                    ], [
                        'name' => '[Death Note] 1-6 Die Hörspielreihe',
                        'path' => 'https://www.audible.de/library/download?asin=3838794176&codec=AAX_44_128',
                    ],
                ],
            ],
            'page 7' => [
                'page7.html',
                [
                    [
                        'name' => '[Ghostsitter] 13 Elementar, mein lieber Tom!',
                        'path' => 'https://www.audible.de/library/download?asin=B0B5H9NVT5&codec=AAX_44_128',
                    ], [
                        'name' => '[Lord Schmetterhemd] 8 Der Tödliche Colt',
                        'path' => 'https://www.audible.de/library/download?asin=B09WQLKHXS&codec=AAX_44_128',
                    ], [
                        'name' => '[Lord Schmetterhemd] 7 Duell in der Mainstreet',
                        'path' => 'https://www.audible.de/library/download?asin=B09TWDGFFH&codec=AAX_44_128',
                    ], [
                        'name' => '[Das Pummeleinhorn] 4 Das Pummeleinhorn',
                        'path' => 'https://www.audible.de/library/download?asin=B09ZLRVZLH&codec=AAX_44_128',
                    ], [
                        'name' => '[Lord Schmetterhemd] 5 Der große Kojote',
                        'path' => 'https://www.audible.de/library/download?asin=B09SQ5M2B6&codec=AAX_44_128',
                    ], [
                        'name' => '[Metro] 1 2033',
                        'path' => 'https://www.audible.de/library/download?asin=B00MGVT0JI&codec=AAX_44_128',
                    ], [
                        'name' => '[Lord Schmetterhemd] 6 Zu viele Zufälle',
                        'path' => 'https://www.audible.de/library/download?asin=B09SHVCT9R&codec=AAX_44_128',
                    ], [
                        'name' => '[Tale of Magic - Die Legende der Magie] 3 Ein gefährlicher Pakt',
                        'path' => 'https://www.audible.de/library/download?asin=B09QMQJ4JT&codec=AAX_44_128',
                    ], [
                        'name' => '[Blackout] 2 Ein Audible Original Hörspiel',
                        'path' => 'https://www.audible.de/library/download?asin=B07N442R8T&codec=AAX_44_128',
                    ],
                ],
            ],
            'page 8' => [
                'page8.html',
                [
                    [
                        'name' => '[Lord Schmetterhemd] 4 Volldampf voraus',
                        'path' => 'https://www.audible.de/library/download?asin=B09SHVFRR5&codec=AAX_44_128',
                    ], [
                        'name' => '[Lord Schmetterhemd] 3 Eine gruselige Wette',
                        'path' => 'https://www.audible.de/library/download?asin=B09MQVHCY8&codec=AAX_44_128',
                    ], [
                        'name' => '[Lord Schmetterhemd] 2 Besuch aus dem Jenseits',
                        'path' => 'https://www.audible.de/library/download?asin=B09MQV3KF8&codec=AAX_44_128',
                    ], [
                        'name' => '[Blackout] 1 Ein Audible Original Hörspiel',
                        'path' => 'https://www.audible.de/library/download?asin=B07L169VJQ&codec=AAX_44_128',
                    ], [
                        'name' => '[Ghostsitter] 12 Sag niemals niemals',
                        'path' => 'https://www.audible.de/library/download?asin=B09N9SHB91&codec=AAX_44_128',
                    ], [
                        'name' => '[Woodwalkers] 6 Tag der Rache',
                        'path' => 'https://www.audible.de/library/download?asin=3401852434&codec=AAX_44_128',
                    ], [
                        'name' => '[Die phantastischen Fälle des Rufus T. Feuerflieg] 12 Endlich Erzfeind',
                        'path' => 'https://www.audible.de/library/download?asin=B09MQTCQ37&codec=AAX_44_128',
                    ], [
                        'name' => '[Lord Schmetterhemd] 1 Spuk auf Bloodywood Castle',
                        'path' => 'https://www.audible.de/library/download?asin=B09MSJWJJ9&codec=AAX_44_128',
                    ], [
                        'name' => '[Game of Thrones - Das Lied von Eis und Feuer] 5 Game of Thrones - Das Lied von Eis und Feuer',
                        'path' => 'https://www.audible.de/library/download?asin=B004V1IHIG&codec=AAX_44_128',
                    ], [
                        'name' => '[Tale of Magic - Die Legende der Magie] 2 Eine dunkle Verschwörung',
                        'path' => 'https://www.audible.de/library/download?asin=B09FQ76TYS&codec=AAX_44_128',
                    ], [
                        'name' => '[Tale of Magic - Die Legende der Magie] 1 Eine geheime Akademie',
                        'path' => 'https://www.audible.de/library/download?asin=3732442454&codec=AAX_44_128',
                    ], [
                        'name' => 'Jacks wundersame Reise mit dem Weihnachtsschwein',
                        'path' => 'https://www.audible.de/library/download?asin=B094Y4MY79&codec=AAX_44_128',
                    ], [
                        'name' => '[Die phantastischen Fälle des Rufus T. Feuerflieg] 11 Der Geist am Set',
                        'path' => 'https://www.audible.de/library/download?asin=B09JT1HCSD&codec=AAX_44_128',
                    ], [
                        'name' => '[Die phantastischen Fälle des Rufus T. Feuerflieg] 10 Dieter, der Vermieter',
                        'path' => 'https://www.audible.de/library/download?asin=B09F712WSW&codec=AAX_44_128',
                    ],
                ],
            ],
            'page 9' => [
                'page9.html',
                [
                    [
                        'name' => '[Ghostsitter] 11 Eisige Stille',
                        'path' => 'https://www.audible.de/library/download?asin=B09F3M3BM4&codec=AAX_44_128',
                    ], [
                        'name' => '[Woodwalkers] 5 Feindliche Spuren',
                        'path' => 'https://www.audible.de/library/download?asin=B07FQDC7VT&codec=AAX_44_128',
                    ], [
                        'name' => '[Die phantastischen Fälle des Rufus T. Feuerflieg] 9 Ein Job für Vierfinger-Franz',
                        'path' => 'https://www.audible.de/library/download?asin=B09C1NMH3Y&codec=AAX_44_128',
                    ], [
                        'name' => '[Die Amazonas-Detektive] 2 Tatort Naturreservat',
                        'path' => 'https://www.audible.de/library/download?asin=B098BKL2MN&codec=AAX_44_128',
                    ], [
                        'name' => 'Reisebüro Jederzeit. Immer Ärger mit dem Chronometer',
                        'path' => 'https://www.audible.de/library/download?asin=B091329Y6M&codec=AAX_44_128',
                    ], [
                        'name' => 'Mr. Parnassus\' Heim für magisch Begabte',
                        'path' => 'https://www.audible.de/library/download?asin=3837156303&codec=AAX_44_128',
                    ], [
                        'name' => '[Ghostsitter] 10 Vier Fäuste gegen Deadwood',
                        'path' => 'https://www.audible.de/library/download?asin=B098FH6FY6&codec=AAX_44_128',
                    ], [
                        'name' => '[Die phantastischen Fälle des Rufus T. Feuerflieg] 8 Der Geist im Dracheneck',
                        'path' => 'https://www.audible.de/library/download?asin=B0972DVKJV&codec=AAX_44_128',
                    ], [
                        'name' => '[Ghostsitter] 9 Zurück nach Damals',
                        'path' => 'https://www.audible.de/library/download?asin=B095CWC5DX&codec=AAX_44_128',
                    ], [
                        'name' => '[Woodwalkers] 4 Fremde Wildnis',
                        'path' => 'https://www.audible.de/library/download?asin=B07BR8SJ53&codec=AAX_44_128',
                    ], [
                        'name' => '[Die phantastischen Fälle des Rufus T. Feuerflieg] 7 Das rätselhafte Rollenspiel',
                        'path' => 'https://www.audible.de/library/download?asin=B0948T1W18&codec=AAX_44_128',
                    ], [
                        'name' => '[#meinAudibleOriginal] Flower Power',
                        'path' => 'https://www.audible.de/library/download?asin=B07TCPL5DS&codec=AAX_44_128',
                    ],
                ],
            ],
            'page 10' => [
                'page10.html',
                [
                    [
                        'name' => '[#meinAudibleOriginal] Die 121ste Umdrehung um die Sonne',
                        'path' => 'https://www.audible.de/library/download?asin=B07TCMB54Y&codec=AAX_44_128',
                    ], [
                        'name' => '[Paw Patrol] 115-116 Berthold und die Superkätzchen',
                        'path' => 'https://www.audible.de/library/download?asin=B08PC7Q7PR&codec=AAX_44_128',
                    ], [
                        'name' => '[Woodwalkers] 3 Hollys Geheimnis',
                        'path' => 'https://www.audible.de/library/download?asin=B07CJQXKFQ&codec=AAX_44_128',
                    ], [
                        'name' => '[Hogwarts Schulbücher] Die Märchen von Beedle dem Barden',
                        'path' => 'https://www.audible.de/library/download?asin=1781105421&codec=AAX_44_128',
                    ], [
                        'name' => '[Paw Patrol] 128-130 Der Sternschnuppen-Regen',
                        'path' => 'https://www.audible.de/library/download?asin=B08WM3F85K&codec=AAX_44_128',
                    ], [
                        'name' => '[Game of Thrones - Das Lied von Eis und Feuer] 4 Game of Thrones - Das Lied von Eis und Feuer',
                        'path' => 'https://www.audible.de/library/download?asin=B004V3VN1C&codec=AAX_44_128',
                    ], [
                        'name' => '[Hollywood liest Weihnachten] 4 Hallojulia',
                        'path' => 'https://www.audible.de/library/download?asin=B07KT13WND&codec=AAX_44_128',
                    ], [
                        'name' => '[Die phantastischen Fälle des Rufus T. Feuerflieg] 6 Zurück in die Gegenwart',
                        'path' => 'https://www.audible.de/library/download?asin=B08ZJL9F8L&codec=AAX_44_128',
                    ], [
                        'name' => '[Game of Thrones - Das Lied von Eis und Feuer] 3 Game of Thrones - Das Lied von Eis und Feuer',
                        'path' => 'https://www.audible.de/library/download?asin=B004V2Z5HG&codec=AAX_44_128',
                    ], [
                        'name' => '[Bibi und Tina 1-50] 43 Konkurrenz für Alex',
                        'path' => 'https://www.audible.de/library/download?asin=B004JVKQ82&codec=AAX_44_128',
                    ], [
                        'name' => '[Bibi und Tina 1-50] 24 Der Millionär',
                        'path' => 'https://www.audible.de/library/download?asin=B004JVIMPG&codec=AAX_44_128',
                    ], [
                        'name' => '[Bibi und Tina 1-50] 37 Der Pferdetausch',
                        'path' => 'https://www.audible.de/library/download?asin=B004JVGJAQ&codec=AAX_44_128',
                    ], [
                        'name' => '[Die Amazonas-Detektive] 1 Verschwörung im Dschungel',
                        'path' => 'https://www.audible.de/library/download?asin=B08R7KHYVT&codec=AAX_44_128',
                    ], [
                        'name' => '[Paw Patrol] Die Super-Hunde. Das Special',
                        'path' => 'https://www.audible.de/library/download?asin=B08PC7XH7X&codec=AAX_44_128',
                    ], [
                        'name' => '[Hollywood liest Weihnachten] 2 O Tannentraum',
                        'path' => 'https://www.audible.de/library/download?asin=B07KT2WM4P&codec=AAX_44_128',
                    ],
                ],
            ],
            'page 11' => [
                'page11.html',
                [
                    [
                        'name' => '[Hollywood liest Weihnachten] 3 Flüsterschnee',
                        'path' => 'https://www.audible.de/library/download?asin=B07KSZX5Q3&codec=AAX_44_128',
                    ], [
                        'name' => '[Ghostsitter] 8 Eine Falle zum Dessert',
                        'path' => 'https://www.audible.de/library/download?asin=B08JH4VFNJ&codec=AAX_44_128',
                    ], [
                        'name' => '[Die Schule der magischen Tiere] 1 Die Schule der magischen Tiere',
                        'path' => 'https://www.audible.de/library/download?asin=B00CI9WBPS&codec=AAX_44_128',
                    ], [
                        'name' => '[Charly und der Wunderwombat Waldemar] 1 Staffel',
                        'path' => 'https://www.audible.de/library/download?asin=B08BBXSFLY&codec=AAX_44_128',
                    ], [
                        'name' => '[Ghostsitter] 7 Die komplette Staffel',
                        'path' => 'https://www.audible.de/library/download?asin=B089QFJ4DR&codec=AAX_44_128',
                    ], [
                        'name' => '[Das Pummeleinhorn] 3 Das Pummeleinhorn',
                        'path' => 'https://www.audible.de/library/download?asin=B08P7SGYTS&codec=AAX_44_128',
                    ], [
                        'name' => '[Ghostsitter] 6 Die komplette Staffel',
                        'path' => 'https://www.audible.de/library/download?asin=B08563MJ7P&codec=AAX_44_128',
                    ], [
                        'name' => '[Die phantastischen Fälle des Rufus T. Feuerflieg] 5 Die neue alte Wohnung',
                        'path' => 'https://www.audible.de/library/download?asin=B08R7PS4N3&codec=AAX_44_128',
                    ], [
                        'name' => '[Ghostsitter] 5 Die komplette Staffel',
                        'path' => 'https://www.audible.de/library/download?asin=B081ZF4X1M&codec=AAX_44_128',
                    ], [
                        'name' => '[Game of Thrones - Das Lied von Eis und Feuer] 2 Game of Thrones - Das Lied von Eis und Feuer',
                        'path' => 'https://www.audible.de/library/download?asin=B004UZH630&codec=AAX_44_128',
                    ], [
                        'name' => '[Scheibenwelt] 20 Schweinsgalopp',
                        'path' => 'https://www.audible.de/library/download?asin=B006LPK9C2&codec=AAX_44_128',
                    ], [
                        'name' => '[Woodwalkers] 2 Gefährliche Freundschaft',
                        'path' => 'https://www.audible.de/library/download?asin=B0798RRSXZ&codec=AAX_44_128',
                    ], [
                        'name' => '[Woodwalkers] 1 Carags Verwandlung',
                        'path' => 'https://www.audible.de/library/download?asin=B0798T2WFP&codec=AAX_44_128',
                    ], [
                        'name' => '[DOORS] 1 X - Dämmerung',
                        'path' => 'https://www.audible.de/library/download?asin=B07GPZ4ML9&codec=AAX_44_128',
                    ], [
                        'name' => '[DOORS] 1 ? - Kolonie',
                        'path' => 'https://www.audible.de/library/download?asin=B07GQ6XL6N&codec=AAX_44_128',
                    ], [
                        'name' => '[Ghostsitter] 4 Die komplette Staffel',
                        'path' => 'https://www.audible.de/library/download?asin=B07QJYYYGL&codec=AAX_44_128',
                    ], [
                        'name' => 'Der Zombie Survival Guide - Überleben unter Untoten',
                        'path' => 'https://www.audible.de/library/download?asin=B005LNBKRS&codec=AAX_44_128',
                    ],
                ],
            ],
            'page 12' => [
                'page12.html',
                [
                    [
                        'name' => '[DOORS - Staffel 2] 0 DOORS - Drei Sekunden',
                        'path' => 'https://www.audible.de/library/download?asin=B07YSR9KCW&codec=AAX_44_128',
                    ], [
                        'name' => '[Ghostsitter] 3 Die komplette Staffel',
                        'path' => 'https://www.audible.de/library/download?asin=B07L96YZPM&codec=AAX_44_128',
                    ], [
                        'name' => '[DOORS Kurzgeschichten] Das Klopfen an der Tür',
                        'path' => 'https://www.audible.de/library/download?asin=B07HJ28BDM&codec=AAX_44_128',
                    ], [
                        'name' => '[DOORS Kurzgeschichten] Glaube & Angst',
                        'path' => 'https://www.audible.de/library/download?asin=B07HY5GMST&codec=AAX_44_128',
                    ], [
                        'name' => '[DOORS Kurzgeschichten] Exponat EA 22542',
                        'path' => 'https://www.audible.de/library/download?asin=B07HY6BK9K&codec=AAX_44_128',
                    ], [
                        'name' => '[DOORS Kurzgeschichten] Fuß in der Tür',
                        'path' => 'https://www.audible.de/library/download?asin=B07HJ1768T&codec=AAX_44_128',
                    ], [
                        'name' => '[DOORS Kurzgeschichten] Tod oder Tür',
                        'path' => 'https://www.audible.de/library/download?asin=B07HJ21S8K&codec=AAX_44_128',
                    ], [
                        'name' => '[Die Mäuseabenteuer] 3 Edison',
                        'path' => 'https://www.audible.de/library/download?asin=B07L15Y1ZW&codec=AAX_44_128',
                    ], [
                        'name' => '[Ghostsitter] 2 Die komplette Staffel',
                        'path' => 'https://www.audible.de/library/download?asin=B07B7NDJL9&codec=AAX_44_128',
                    ], [
                        'name' => '[Die phantastischen Fälle des Rufus T. Feuerflieg] 4 Der Fall Merle',
                        'path' => 'https://www.audible.de/library/download?asin=B08FJFXY15&codec=AAX_44_128',
                    ], [
                        'name' => '[Ghostsitter] 1 Die komplette Staffel',
                        'path' => 'https://www.audible.de/library/download?asin=B076CG4RZJ&codec=AAX_44_128',
                    ],
                ],
            ],
            'page 13' => [
                'page13.html',
                [
                    [
                        'name' => '[Game of Thrones - Das Lied von Eis und Feuer] 1 Game of Thrones - Das Lied von Eis und Feuer',
                        'path' => 'https://www.audible.de/library/download?asin=B004UZRCVQ&codec=AAX_44_128',
                    ], [
                        'name' => '[Das Pummeleinhorn] 2 Das Pummeleinhorn',
                        'path' => 'https://www.audible.de/library/download?asin=B0892MP2SW&codec=AAX_44_128',
                    ], [
                        'name' => '[DOORS Kurzgeschichten] 0 DOORS - Der Beginn',
                        'path' => 'https://www.audible.de/library/download?asin=B07GH1TK6H&codec=AAX_44_128',
                    ], [
                        'name' => '[Lou Clark] Auf diese Art zusammen',
                        'path' => 'https://www.audible.de/library/download?asin=3732404471&codec=AAX_44_128',
                    ], [
                        'name' => '[Die phantastischen Fälle des Rufus T. Feuerflieg] 3 Alles für die Katz',
                        'path' => 'https://www.audible.de/library/download?asin=B088GLXF8W&codec=AAX_44_128',
                    ], [
                        'name' => '[Die phantastischen Fälle des Rufus T. Feuerflieg] 1 Kreszenzia kommt',
                        'path' => 'https://www.audible.de/library/download?asin=B082YCNG9Z&codec=AAX_44_128',
                    ], [
                        'name' => '[#meinAudibleOriginal] Das Experiment',
                        'path' => 'https://www.audible.de/library/download?asin=B07TCPS8MR&codec=AAX_44_128',
                    ], [
                        'name' => '[#meinAudibleOriginal] Die Rabenkönigin',
                        'path' => 'https://www.audible.de/library/download?asin=B07TCP3CXM&codec=AAX_44_128',
                    ], [
                        'name' => '[#meinAudibleOriginal] Wurststullen auf Bali',
                        'path' => 'https://www.audible.de/library/download?asin=B07TBNS36H&codec=AAX_44_128',
                    ], [
                        'name' => 'ALIEN - In den Schatten - Die 1. Staffel (Kostenlose Hörprobe)',
                        'path' => 'https://www.audible.de/library/download?asin=B07RDQSFD8&codec=AAX_44_128',
                    ], [
                        'name' => 'Das Starling Projekt - Das ungekürzte Hörspiel (Kostenlose Hörprobe)',
                        'path' => 'https://www.audible.de/library/download?asin=B00Z7ATXZG&codec=AAX_44_128',
                    ], [
                        'name' => 'Jonah - Die Lehrjahre (Der König der purpurnen Stadt 1) - Kostenlose Hörprobe',
                        'path' => 'https://www.audible.de/library/download?asin=B00U8Q6KY6&codec=AAX_44_128',
                    ], [
                        'name' => 'Gebrüder Grimm - Dornröschen (aus - "Kinder- und Hausmärchen")',
                        'path' => 'https://www.audible.de/library/download?asin=B00B4FPR76&codec=AAX_44_128',
                    ], [
                        'name' => 'Das Lied von Eis und Feuer 1 (Kostenlose Hörprobe)',
                        'path' => 'https://www.audible.de/library/download?asin=B00857RHQU&codec=AAX_44_128',
                    ],
                ],
            ],
            'page 14' => [
                'page14.html',
                [
                    [
                        'name' => 'Die Grube und das Pendel',
                        'path' => 'https://www.audible.de/library/download?asin=B004UVDZVQ&codec=AAX_44_128',
                    ], [
                        'name' => '[Die phantastischen Fälle des Rufus T. Feuerflieg] 2 Der Gartengnom',
                        'path' => 'https://www.audible.de/library/download?asin=B086X2WWNH&codec=AAX_44_128',
                    ], [
                        'name' => 'Gullivers Reisen',
                        'path' => 'https://www.audible.de/library/download?asin=B004UZX5EY&codec=AAX_44_128',
                    ], [
                        'name' => 'Märchen von einem, der auszog, das Fürchten zu lernen',
                        'path' => 'https://www.audible.de/library/download?asin=B00NNSFQNW&codec=AAX_44_128',
                    ], [
                        'name' => 'Das Dschungelbuch',
                        'path' => 'https://www.audible.de/library/download?asin=B004UW0R8O&codec=AAX_44_128',
                    ], [
                        'name' => '20.000 Meilen unter dem Meer',
                        'path' => 'https://www.audible.de/library/download?asin=B004V0AJD8&codec=AAX_44_128',
                    ], [
                        'name' => 'In 80 Tagen um die Welt',
                        'path' => 'https://www.audible.de/library/download?asin=B004V296GW&codec=AAX_44_128',
                    ], [
                        'name' => 'Reise zum Mittelpunkt der Erde',
                        'path' => 'https://www.audible.de/library/download?asin=B004V3FARK&codec=AAX_44_128',
                    ], [
                        'name' => 'Alice im Wunderland',
                        'path' => 'https://www.audible.de/library/download?asin=B004V5HDQO&codec=AAX_44_128',
                    ], [
                        'name' => '[Das Pummeleinhorn] 1 Das Pummeleinhorn',
                        'path' => 'https://www.audible.de/library/download?asin=B07TMBXDXQ&codec=AAX_44_128',
                    ],
                ],
            ],
        ];
    }
}
