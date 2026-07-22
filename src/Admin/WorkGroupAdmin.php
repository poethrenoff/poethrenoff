<?php

namespace App\Admin;

use App\Entity\WorkGroup;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class WorkGroupAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('parent', ModelAutocompleteType::class, [
                'class' => WorkGroup::class,
                'property' => 'title',
                'required' => false,
            ])
            ->add('title', TextType::class)
            ->add('comment', TextType::class, ['required' => false])
            ->add('position', NumberType::class)
            ->add('isFavorite', CheckboxType::class, ['required' => false])
            ->add('isActive', CheckboxType::class, ['required' => false])
        ;
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('id')
            ->add('title')
            ->add('isFavorite')
            ->add('isActive')
        ;
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('title')
            ->add('parent')
            ->add('position', null, ['editable' => true])
            ->add('isFavorite', null, ['editable' => true])
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
            ->add('parent')
            ->add('title')
            ->add('comment')
            ->add('position')
            ->add('isActive')
            ->add('isFavorite')
        ;
    }
}
