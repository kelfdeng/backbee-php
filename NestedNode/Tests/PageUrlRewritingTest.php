<?php

namespace BackBee\NestedNode\Tests;

use BackBee\NestedNode\Page;
use BackBee\Site\Site;
use BackBee\Tests\TestCase;

/**
 * This class tests these classes to validate the page's url rewrite process:
 *     - BackBee\Event\Listener\RewritingListener
 *     - BackBee\NestedNode\Page
 *     - BackBee\Rewriting\UrlGenerator
 *
 * @author Eric Chau <eric.chau@lp-digital.fr>
 */
class PageUrlRewritingTest extends TestCase
{
    private $app;
    private $em;
    private $root;

    public function setUp()
    {
        $this->app = $this->getApplication();
        $this->em = $this->app->getEntityManager();

        $this->initDb($this->app);

        $site = new Site();
        $site->setLabel('foobar');
        $this->em->persist($site);
        $this->em->flush($site);

        $this->root = $this->createRootPage();
        $this->root->setSite($site);
        $this->root->setUrl('/');

        $layout = $this->root->getLayout();
        $layout->setLabel('foobar');
        $layout->setPath('/foobar');
        $this->em->persist($layout);
        $this->em->flush($layout);

        $this->em->persist($this->root);
        $this->em->flush($this->root);
    }

    public function testGenerateUrlOnNullOrEmpty()
    {
        // url === null tests
        $page = $this->generatePage('null');

        $this->assertNull($page->getUrl(false));

        $this->em->persist($page);
        $this->em->flush($page);

        $this->assertEquals('/null', $page->getUrl());

        // url === '' empty string tests
        $page = $this->generatePage('Empty string', '');

        $this->assertEquals('', $page->getUrl(false));

        $this->em->persist($page);
        $this->em->flush($page);

        $this->assertEquals('/empty-string', $page->getUrl());
    }

    public function testGenerateUniqueUrl()
    {
        $this->assertTrue($this->app->getContainer()->get('rewriting.urlgenerator')->isPreserveUnicity());
        $this->assertEquals('/backbee', $this->generatePage('backbee', null, true)->getUrl());
        $this->assertEquals('/backbee-1', $this->generatePage('backbee', null, true)->getUrl());
        $this->assertEquals('/backbee-2', $this->generatePage('backbee', null, true)->getUrl());
    }

    public function testReplaceOldDeletedUrl()
    {
        $this->assertTrue($this->app->getContainer()->get('rewriting.urlgenerator')->isPreserveUnicity());
        $pageToDelete = $this->generatePage('backbee', null, true);
        $otherPageToDelete = $this->generatePage('backbee', null, true);
        $this->assertEquals('/backbee', $pageToDelete->getUrl());
        $this->assertEquals('/backbee-1', $otherPageToDelete->getUrl());
        $this->assertEquals('/backbee-2', $this->generatePage('backbee', null, true)->getUrl());

        $this->em->remove($pageToDelete);
        $this->em->flush($pageToDelete);

        $this->assertNull($this->em->getRepository('BackBee\NestedNode\Page')->findOneBy(['_url' => '/backbee']));

        $this->assertEquals('/backbee', $this->generatePage('backbee', null, true)->getUrl());

        $this->em->remove($otherPageToDelete);
        $this->em->flush($otherPageToDelete);

        $this->assertNull($this->em->getRepository('BackBee\NestedNode\Page')->findOneBy(['_url' => '/backbee-1']));

        $this->assertEquals('/backbee-1', $this->generatePage('backbee', null, true)->getUrl());
    }

    public function testManualSetUrlAndPreserveUnicity()
    {
        $this->assertTrue($this->app->getContainer()->get('rewriting.urlgenerator')->isPreserveUnicity());
        $this->assertEquals('/foo/bar', $this->generatePage('backbee', '/foo/bar', true)->getUrl());
        $this->assertEquals('/foo/bar-1', $this->generatePage('backbee', '/foo/bar', true)->getUrl());
    }

    public function testUrlIsAutoGeneratedAsLongAsStateIsOfflineAndTitleChange()
    {
        $page = $this->generatePage('backbee', null, true);
        $this->assertEquals('/backbee', $page->getUrl());

        $page->setTitle('LP Digital');
        $this->assertEquals('/backbee', $page->getUrl());
        $this->em->flush($page);
        $this->assertEquals('/lp-digital', $page->getUrl());

        $page->setTitle('foo bar');
        $this->assertEquals('/lp-digital', $page->getUrl());
        $this->em->flush($page);
        $this->assertEquals('/foo-bar', $page->getUrl());
    }

    public function testChangeUrlOfPageOnlineWithPreserveOnline()
    {
        $this->assertTrue($this->app->getContainer()->get('rewriting.urlgenerator')->isPreserveOnline());
        $page = $this->generatePage('backbee', null, true);
        $page->setState(Page::STATE_OFFLINE);
        $this->assertEquals('/backbee', $page->getUrl());

        $page->setTitle('foo bar');
        $this->em->flush($page);
        $this->assertEquals('/foo-bar', $page->getUrl());

        // RewritingListener also detects if the previous page's state is equal to online or not to determine
        // if a very last autogenerate url is required
        $page->setState(Page::STATE_ONLINE);
        $page->setTitle('LP Digital');
        $this->em->flush($page);
        $this->assertEquals('/lp-digital', $page->getUrl());

        $page->setTitle('This is a test');
        $this->em->flush($page);
        $this->assertEquals('/lp-digital', $page->getUrl());

        // property preserveOnline only prevent RewritingListener and UrlGenerator to autogenerate url
        // but it's still possible to change manually the page url (no matters if preserveOnline is true or false)
        $page->setUrl('/nestednode-page');
        $this->em->flush($page);
        $this->assertEquals('/nestednode-page', $page->getUrl());
    }

    private function generatePage($title = 'backbee', $url = null, $doPersist = false)
    {
        $page = new Page();
        $page->setRoot($this->root);
        $page->setParent($this->root);
        $page->setTitle($title);
        $page->setUrl($url);

        if ($doPersist) {
            $this->em->persist($page);
            $this->em->flush($page);
        }

        return $page;
    }
}