<?php

namespace Crm\UsersModule\Forms;

use Crm\UsersModule\Repository\UsersRepository;
use Nette\Application\UI\Form;
use Nette\Database\Table\IRow;
use Tomaj\Form\Renderer\BootstrapInlineRenderer;

class UserNoteFormFactory
{
    /** @var UsersRepository */
    protected $usersRepository;

    /* callback function */
    public $onUpdate;

    /** @var IRow */
    private $user;

    public function __construct(UsersRepository $usersRepository)
    {
        $this->usersRepository = $usersRepository;
    }

    /**
     * @params $user
     * @return Form
     */
    public function create(IRow $user)
    {
        $form = new Form;
        $this->user = $user;

        $form->setRenderer(new BootstrapInlineRenderer());
        $form->addProtection();

        $form->addTextArea('note', '')
            ->setAttribute('placeholder', 'Sem môžete napísať poznámku')
            ->getControlPrototype()->addAttributes(['class' => 'autosize']);

        $form->addSubmit('send', 'Uložiť')
            ->setAttribute('class', 'btn btn-primary')
            ->getControlPrototype()
            ->setName('button')
            ->setHtml('<i class="fa fa-save"></i> Uložiť');

        $form->setDefaults([
            'note' => $this->user->note,
        ]);

        $form->onSuccess[] = [$this, 'formSucceeded'];
        return $form;
    }

    public function formSucceeded($form, $values)
    {
        $this->usersRepository->update($this->user, ['note' => $values['note']]);
        $this->onUpdate->__invoke($form, $this->user);
    }
}
