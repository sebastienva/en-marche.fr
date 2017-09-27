<?php

namespace Tests\AppBundle\Controller\EnMarche;

use AppBundle\DataFixtures\ORM\LoadAdherentData;
use AppBundle\DataFixtures\ORM\LoadAdminData;
use AppBundle\Mailjet\Message\CommitteeApprovalConfirmationMessage;
use AppBundle\Mailjet\Message\CommitteeApprovalReferentMessage;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\AppBundle\Controller\ControllerTestTrait;
use Tests\AppBundle\MysqlWebTestCase;

/**
 * @group functional
 */
class AdminCommitteeControllerTest extends MysqlWebTestCase
{
    use ControllerTestTrait;

    private $committeeRepository;

    public function testApproveAction()
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/admin/login');

        // connect as admin
        $this->client->submit($crawler->selectButton('Je me connecte')->form([
            '_admin_email' => 'admin@en-marche-dev.fr',
            '_admin_password' => 'admin',
        ]));

        $uri = sprintf('/admin/committee/%s/approve', 11);
        $this->client->request(Request::METHOD_GET, $uri);
        $this->assertResponseStatusCode(Response::HTTP_FOUND, $this->client->getResponse());

        $committee = $this->committeeRepository->findOneByUuid(LoadAdherentData::COMMITTEE_11_UUID);

        $this->assertTrue($committee->isApproved());
        $this->assertCount(1, $this->getMailjetEmailRepository()->findRecipientMessages(CommitteeApprovalConfirmationMessage::class, 'jacques.picard@en-marche.fr'));
        $this->assertCount(1, $this->getMailjetEmailRepository()->findRecipientMessages(CommitteeApprovalReferentMessage::class, 'referent@en-marche-dev.fr'));
    }

    protected function setUp()
    {
        parent::setUp();

        $this->init([
            LoadAdminData::class,
            LoadAdherentData::class,
        ]);

        $this->committeeRepository = $this->getCommitteeRepository();
        $this->emailRepository = $this->getMailjetEmailRepository();
    }

    protected function tearDown()
    {
        $this->kill();

        $this->committeeRepository = null;
        $this->emailRepository = null;

        parent::tearDown();
    }
}
