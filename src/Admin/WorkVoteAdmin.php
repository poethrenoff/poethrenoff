<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class WorkVoteAdmin extends AbstractAdmin
{
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        // Typically votes are not edited manually, but let's keep it for now
        // $collection->remove('create');
        // $collection->remove('edit');
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('work', null, [
                'property' => 'title',
            ])
            ->add('ipHash', TextType::class)
            ->add('sessionHash', TextType::class, ['required' => false])
            ->add('voteType', ChoiceType::class, [
                'choices' => [
                    'Like' => 'like',
                    'Dislike' => 'dislike',
                ],
            ])
            ->add('createdAt', DateTimeType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
        ;
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('work')
            ->add('voteType')
            ->add('ipHash')
        ;
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('id')
            ->add('work')
            ->add('voteType')
            ->add('ipHash')
            ->add('createdAt')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'show' => [],
                    'delete' => [],
                ],
            ])
        ;
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id')
            ->add('work')
            ->add('ipHash')
            ->add('sessionHash')
            ->add('voteType')
            ->add('createdAt')
        ;
    }
}
