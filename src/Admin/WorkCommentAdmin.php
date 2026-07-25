<?php

namespace App\Admin;

use App\Entity\Work;
use App\Entity\WorkComment;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class WorkCommentAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('work', ModelAutocompleteType::class, [
                'class' => Work::class,
                'property' => 'title',
            ])
            ->add('parent', ModelAutocompleteType::class, [
                'class' => WorkComment::class,
                'property' => 'id',
                'required' => false,
            ])
            ->add('author', TextType::class)
            ->add('content', null, [
                'attr' => ['class' => 'trumbowyg-editor'],
            ])
            ->add('info', TextType::class, [
                'required' => false,
            ])
            ->add('createdAt', DateTimeType::class, [
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('isActive', CheckboxType::class, [
                'required' => false,
            ])
        ;
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('id')
            ->add('work')
            ->add('author')
            ->add('isActive')
        ;
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('id')
            ->add('work')
            ->add('author')
            ->add('createdAt')
            ->add('isActive', null, ['editable' => true])
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
            ->add('work')
            ->add('parent')
            ->add('author')
            ->add('content')
            ->add('info')
            ->add('createdAt')
            ->add('isActive')
        ;
    }
}
