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
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class WorkAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('group', ModelAutocompleteType::class, [
                'class' => WorkGroup::class,
                'property' => 'title',
            ])
            ->add('title', TextType::class)
            ->add('text', TextareaType::class, [
                'attr' => ['rows' => 15]
            ])
            ->add('comment', TextType::class, ['required' => false])
            ->add('position', NumberType::class)
            ->add('isActive', CheckboxType::class, ['required' => false])
            ->add('likesCount', IntegerType::class)
            ->add('dislikesCount', IntegerType::class)
        ;
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('id')
            ->add('title')
            ->add('group')
            ->add('isActive')
        ;
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('title')
            ->add('group')
            ->add('position', null, ['editable' => true])
            ->add('isActive', null, ['editable' => true])
            ->add('likesCount')
            ->add('dislikesCount')
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
            ->add('group')
            ->add('title')
            ->add('text')
            ->add('comment')
            ->add('position')
            ->add('isActive')
            ->add('likesCount')
            ->add('dislikesCount')
        ;
    }
}
