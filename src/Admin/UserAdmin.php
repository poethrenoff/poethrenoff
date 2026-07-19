<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserAdmin extends AbstractAdmin
{
    private UserPasswordHasherInterface $passwordHasher;

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
            ->addIdentifier('email')
            ->add('roles')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'show' => [],
                    'edit' => [],
                    'delete' => [],
                ],
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
        if (!$this->getForm()->has('plainPassword')) {
            return;
        }

        $plainPassword = $this->getForm()->get('plainPassword')->getData();
        if ($plainPassword) {
            $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));
        }
    }
}
