<?php

namespace App\Admin;

use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelType;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;

class BlogPostAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('content', TextareaType::class, [
                'attr' => ['rows' => 10]
            ])
            ->add('publishedAt', DateTimeType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('isActive', CheckboxType::class, [
                'required' => false,
            ])
            ->add('tags', ModelType::class, [
                'class' => 'App\Entity\Tag',
                'property' => 'title',
                'multiple' => true,
                'required' => false,
            ])
        ;
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('content')
            ->add('isActive')
            ->add('tags')
        ;
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('id')
            ->add('content', null, [
                'template' => '@SonataAdmin/CRUD/list_string.html.twig',
            ])
            ->add('publishedAt')
            ->add('isActive', null, ['editable' => true])
            ->add('tags')
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
            ->add('content')
            ->add('publishedAt')
            ->add('isActive')
            ->add('tags')
        ;
    }
}
