<?php

namespace App\Admin;

use App\Entity\Work;
use App\Entity\WorkGroup;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\DoctrineORMAdminBundle\Filter\ModelFilter;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractAdmin<Work>
 */
class WorkAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('group', ModelAutocompleteType::class, [
                'class' => WorkGroup::class,
                'property' => 'title',
                'minimum_input_length' => 1,
                'btn_add' => false,
            ])
            ->add('title', TextType::class)
            ->add('text', TextareaType::class, [
                'attr' => ['rows' => 15]
            ])
            ->add('comment', TextType::class, ['required' => false])
            ->add('likesCount', IntegerType::class)
            ->add('dislikesCount', IntegerType::class)
            ->add('position', NumberType::class)
            ->add('isActive', CheckboxType::class, ['required' => false])
        ;
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('id')
            ->add('title')
            ->add('group', ModelFilter::class, [
                'field_type' => ModelAutocompleteType::class,
                'field_options' => [
                    'class' => WorkGroup::class,
                    'property' => 'title',
                    'minimum_input_length' => 1,
                ]
            ])
            ->add('isActive')
        ;
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('id', null, [
                'route' => ['name' => 'edit'],
            ])
            ->add('title', null, [
                'header_style' => 'width: 40%',
            ])
            ->add('group')
            ->add('likesCount', null, ['editable' => true])
            ->add('dislikesCount', null, ['editable' => true])
            ->add('position', null, ['editable' => true])
            ->add('isActive', null, ['editable' => true])
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

    protected function configureDefaultSortValues(array &$sortValues): void
    {
        $sortValues['_sort_by'] = 'id';
        $sortValues['_sort_order'] = 'ASC';
    }
}
