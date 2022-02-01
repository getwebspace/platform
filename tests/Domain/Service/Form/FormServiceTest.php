<?php declare(strict_types=1);

namespace tests\Domain\Service\Form;

use App\Domain\Entities\Form;
use App\Domain\Repository\FormRepository;
use App\Domain\Service\Form\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Form\Exception\FormNotFoundException;
use App\Domain\Service\Form\Exception\MissingTitleValueException;
use App\Domain\Service\Form\Exception\TitleAlreadyExistsException;
use App\Domain\Service\Form\FormService;
use Doctrine\ORM\EntityManager;
use tests\TestCase;

/**
 * @internal
 * @coversNothing
 */
class FormServiceTest extends TestCase
{
    protected FormService $service;

    public function setUp(): void
    {
        parent::setUp();

        $this->service = $this->getService(FormService::class);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'title' => $this->getFaker()->title,
            'address' => 'some-custom-address',
            'template' => $this->getFaker()->word,
            'authorSend' => $this->getFaker()->boolean,
            'recaptcha' => $this->getFaker()->boolean,
            'origin' => [$this->getFaker()->domainName],
            'mailto' => [$this->getFaker()->email],
            'duplicate' => $this->getFaker()->url,
        ];

        $form = $this->service->create($data);
        $this->assertInstanceOf(Form::class, $form);
        $this->assertSame($data['title'], $form->getTitle());
        $this->assertSame($data['address'], $form->getAddress());
        $this->assertSame($data['template'], $form->getTemplate());
        $this->assertSame($data['authorSend'], $form->getAuthorSend());
        $this->assertSame($data['recaptcha'], $form->getRecaptcha());
        $this->assertSame($data['origin'], $form->getOrigin());
        $this->assertSame($data['mailto'], $form->getMailto());
        $this->assertSame($data['duplicate'], $form->getDuplicate());

        /** @var FormRepository $formRepo */
        $formRepo = $this->em->getRepository(Form::class);
        $f = $formRepo->findOneByTitle($data['title']);
        $this->assertInstanceOf(Form::class, $f);
        $this->assertSame($data['title'], $f->getTitle());
        $this->assertSame($data['address'], $f->getAddress());
        $this->assertSame($data['template'], $f->getTemplate());
        $this->assertSame($data['authorSend'], $f->getAuthorSend());
        $this->assertSame($data['recaptcha'], $f->getRecaptcha());
        $this->assertSame($data['origin'], $f->getOrigin());
        $this->assertSame($data['mailto'], $f->getMailto());
        $this->assertSame($data['duplicate'], $f->getDuplicate());
    }

    public function testCreateWithMissingTitleValue(): void
    {
        $this->expectException(MissingTitleValueException::class);

        $this->service->create();
    }

    public function testCreateWithTitleExistent(): void
    {
        $this->expectException(TitleAlreadyExistsException::class);

        $data = [
            'title' => $this->getFaker()->title,
            'address' => 'some-custom-address',
            'template' => $this->getFaker()->word,
        ];

        $form = (new Form())
            ->setTitle($data['title'])
            ->setAddress($data['address'])
            ->setTemplate($data['template']);

        $this->em->persist($form);
        $this->em->flush();

        $this->service->create($data);
    }

    public function testCreateWithAddressExistent(): void
    {
        $this->expectException(AddressAlreadyExistsException::class);

        $data = [
            'title' => $this->getFaker()->title,
            'address' => 'some-custom-address',
            'template' => $this->getFaker()->word,
        ];

        $form = (new Form())
            ->setTitle($data['title'] . '-miss')
            ->setAddress($data['address'])
            ->setTemplate($data['template']);

        $this->em->persist($form);
        $this->em->flush();

        $this->service->create($data);
    }

    public function testReadSuccess1(): void
    {
        $data = [
            'title' => $this->getFaker()->title,
            'address' => 'some-custom-address',
            'template' => $this->getFaker()->word,
        ];

        $this->service->create($data);

        $form = $this->service->read(['title' => $data['title']]);
        $this->assertInstanceOf(Form::class, $form);
        $this->assertSame($data['title'], $form->getTitle());
    }

    public function testReadSuccess2(): void
    {
        $data = [
            'title' => $this->getFaker()->title,
            'address' => 'some-custom-address',
            'template' => $this->getFaker()->word,
        ];

        $this->service->create($data);

        $form = $this->service->read(['address' => $data['address']]);
        $this->assertInstanceOf(Form::class, $form);
        $this->assertSame($data['address'], $form->getAddress());
    }

    public function testReadWithFormNotFound(): void
    {
        $this->expectException(FormNotFoundException::class);

        $this->service->read(['title' => $this->getFaker()->title]);
    }

    public function testUpdate(): void
    {
        $form = $this->service->create([
            'title' => $this->getFaker()->title,
            'address' => 'some-custom-address',
            'template' => $this->getFaker()->word,
            'authorSend' => $this->getFaker()->boolean,
            'recaptcha' => $this->getFaker()->boolean,
            'origin' => [$this->getFaker()->domainName],
            'mailto' => [$this->getFaker()->email],
            'duplicate' => $this->getFaker()->url,
        ]);

        $data = [
            'title' => $this->getFaker()->title,
            'address' => 'some-custom-address',
            'template' => $this->getFaker()->word,
            'authorSend' => $this->getFaker()->boolean,
            'recaptcha' => $this->getFaker()->boolean,
            'origin' => [$this->getFaker()->domainName],
            'mailto' => [$this->getFaker()->email],
            'duplicate' => $this->getFaker()->url,
        ];

        $form = $this->service->update($form, $data);
        $this->assertInstanceOf(Form::class, $form);
        $this->assertSame($data['title'], $form->getTitle());
        $this->assertSame($data['address'], $form->getAddress());
        $this->assertSame($data['template'], $form->getTemplate());
        $this->assertSame($data['authorSend'], $form->getAuthorSend());
        $this->assertSame($data['recaptcha'], $form->getRecaptcha());
        $this->assertSame($data['origin'], $form->getOrigin());
        $this->assertSame($data['mailto'], $form->getMailto());
        $this->assertSame($data['duplicate'], $form->getDuplicate());
    }

    public function testUpdateWithFormNotFound(): void
    {
        $this->expectException(FormNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $form = $this->service->create([
            'title' => $this->getFaker()->title,
            'address' => 'some-custom-address',
            'template' => $this->getFaker()->word,
        ]);

        $result = $this->service->delete($form);

        $this->assertTrue($result);
    }

    public function testDeleteWithFormNotFound(): void
    {
        $this->expectException(FormNotFoundException::class);

        $this->service->delete(null);
    }
}
