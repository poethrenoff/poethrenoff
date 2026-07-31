<?php

namespace App\Admin;

use App\Entity\User;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\FieldDescription\FieldDescriptionInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @extends AbstractAdmin<User>
 */
class UserAdmin extends AbstractAdmin
{
    private UserPasswordHasherInterface $passwordHasher;

    #[Required]
    public function setPasswordHasher(UserPasswordHasherInterface $passwordHasher): void
    {
        $this->passwordHasher = $passwordHasher;
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('email', EmailType::class)
            ->add('roles', ChoiceType::class, [
                'choices' => [
                    'User' => 'ROLE_USER',
                    'Admin' => 'ROLE_ADMIN',
                ],
                'multiple' => true,
            ])
            ->add('plainPassword', PasswordType::class, [
                'required' => $this->isCurrentRoute('create'),
                'mapped' => false,
                'label' => 'Password',
            ])
        ;
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('email')
        ;
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('id', null, [
                'route' => ['name' => 'edit'],
            ])
            ->add('email', null, [
                'header_style' => 'width: 50%',
            ])
            ->add('roles', FieldDescriptionInterface::TYPE_CHOICE, [
                'multiple' => true,
            ])
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'show' => [],
                    'edit' => [],
                    'delete' => [],
                ],
                'header_style' => 'width: 210px',
            ])
        ;
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id')
            ->add('email')
            ->add('roles')
        ;
    }

    protected function prePersist(object $object): void
    {
        $this->updatePassword($object);
    }

    protected function preUpdate(object $object): void
    {
        $this->updatePassword($object);
    }

    private function updatePassword(object $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        if (!$this->getForm()->has('plainPassword')) {
            return;
        }

        $plainPassword = $this->getForm()->get('plainPassword')->getData();
        if (!is_string($plainPassword) || $plainPassword === '') {
            return;
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
    }

    protected function configureDefaultSortValues(array &$sortValues): void
    {
        $sortValues['_sort_by'] = 'id';
        $sortValues['_sort_order'] = 'ASC';
    }
}
