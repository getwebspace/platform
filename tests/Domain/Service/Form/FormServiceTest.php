<?php declare(strict_types=1);

namespace tests\Domain\Service\Form;

use App\Domain\Models\Form;
use App\Domain\Service\Form\Exception\AddressAlreadyExistsException;
use App\Domain\Service\Form\Exception\FormNotFoundException;
use App\Domain\Service\Form\Exception\MissingTitleValueException;
use App\Domain\Service\Form\Exception\TitleAlreadyExistsException;
use App\Domain\Service\Form\FormService;
use tests\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
class FormServiceTest extends TestCase
{
    protected FormService $service;

    public function setUp(): void
    {
        $this->service = $this->getService(FormService::class);
    }

    public function testCreateSuccess(): void
    {
        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'template' => $this->getFaker()->word,
            'templateFile' => $this->getFaker()->word,
            'recaptcha' => $this->getFaker()->boolean,
            'authorSend' => $this->getFaker()->boolean,
            'origin' => $this->getFaker()->domainName,
            'mailto' => $this->getFaker()->email,
            'duplicate' => $this->getFaker()->url,
        ];

        $form = $this->service->create($data);
        $this->assertInstanceOf(Form::class, $form);
        $this->assertEquals($data['title'], $form->title);
        $this->assertEquals($data['address'], $form->address);
        $this->assertEquals($data['template'], $form->template);
        $this->assertEquals($data['templateFile'], $form->templateFile);
        $this->assertEquals($data['recaptcha'], $form->recaptcha);
        $this->assertEquals($data['authorSend'], $form->authorSend);
        $this->assertEquals([$data['origin']], $form->origin);
        $this->assertEquals([$data['mailto']], $form->mailto);
        $this->assertEquals($data['duplicate'], $form->duplicate);
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
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'template' => $this->getFaker()->word,
        ];

        Form::create($data);

        $this->service->create($data);
    }

    public function testCreateWithAddressExistent(): void
    {
        $this->expectException(AddressAlreadyExistsException::class);

        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'template' => $this->getFaker()->word,
        ];

        Form::create($data);

        $this->service->create(array_merge($data, [
            'title' => implode(' ', $this->getFaker()->words(3)),
        ]));
    }

    public function testReadSuccess1(): void
    {
        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'template' => $this->getFaker()->word,
        ];

        $this->service->create($data);

        $form = $this->service->read(['title' => $data['title']]);
        $this->assertInstanceOf(Form::class, $form);
        $this->assertEquals($data['title'], $form->title);
    }

    public function testReadSuccess2(): void
    {
        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'template' => $this->getFaker()->word,
        ];

        $this->service->create($data);

        $form = $this->service->read(['address' => $data['address']]);
        $this->assertInstanceOf(Form::class, $form);
        $this->assertEquals($data['address'], $form->address);
    }

    public function testReadWithFormNotFound(): void
    {
        $this->expectException(FormNotFoundException::class);

        $this->service->read(['title' => $this->getFaker()->word]);
    }

    public function testUpdate(): void
    {
        $form = $this->service->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'template' => $this->getFaker()->word,
            'templateFile' => $this->getFaker()->word,
            'recaptcha' => $this->getFaker()->boolean,
            'authorSend' => $this->getFaker()->boolean,
            'origin' => $this->getFaker()->domainName,
            'mailto' => $this->getFaker()->email,
            'duplicate' => $this->getFaker()->url,
        ]);

        $data = [
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
            'template' => $this->getFaker()->word,
            'templateFile' => $this->getFaker()->word,
            'recaptcha' => $this->getFaker()->boolean,
            'authorSend' => $this->getFaker()->boolean,
            'origin' => $this->getFaker()->domainName,
            'mailto' => $this->getFaker()->email,
            'duplicate' => $this->getFaker()->url,
        ];

        $form = $this->service->update($form, $data);
        $this->assertInstanceOf(Form::class, $form);
        $this->assertEquals($data['title'], $form->title);
        $this->assertEquals($data['address'], $form->address);
        $this->assertEquals($data['template'], $form->template);
        $this->assertEquals($data['templateFile'], $form->templateFile);
        $this->assertEquals($data['recaptcha'], $form->recaptcha);
        $this->assertEquals($data['authorSend'], $form->authorSend);
        $this->assertEquals([$data['origin']], $form->origin);
        $this->assertEquals([$data['mailto']], $form->mailto);
        $this->assertEquals($data['duplicate'], $form->duplicate);
    }

    public function testUpdateWithFormNotFound(): void
    {
        $this->expectException(FormNotFoundException::class);

        $this->service->update(null);
    }

    public function testDeleteSuccess(): void
    {
        $form = $this->service->create([
            'title' => implode(' ', $this->getFaker()->words(3)),
            'address' => implode('-', $this->getFaker()->words(4)),
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
