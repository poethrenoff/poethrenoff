<?php

namespace App\Admin;

use App\Entity\BlogPost;
use App\Entity\Tag;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\FieldDescription\FieldDescriptionInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\DoctrineORMAdminBundle\Filter\DateTimeRangeFilter;
use Sonata\DoctrineORMAdminBundle\Filter\ModelFilter;
use Sonata\Form\Type\DateTimeRangePickerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;

/**
 * @extends AbstractAdmin<BlogPost>
 */
class BlogPostAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('content', null, [
                'attr' => ['class' => 'trumbowyg-editor'],
            ])
            ->add('publishedAt', DateTimeType::class)
            ->add('isActive', CheckboxType::class, [
                'required' => false,
            ])
            ->add('tags', ModelAutocompleteType::class, [
                'class' => Tag::class,
                'property' => 'title',
                'minimum_input_length' => 1,
                'multiple' => true,
                'required' => false,
            ])
        ;
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('id')
            ->add('content')
            ->add(
                'publishedAt',
                DateTimeRangeFilter::class,
                [
                    'field_type' => DateTimeRangePickerType::class,
                    'field_options' => [
                        'field_options' => [
                            'format' => 'yyyy-MM-dd HH:mm',
                        ]
                    ]
                ]
            )
            ->add('isActive')
            ->add('tags', ModelFilter::class, [
                'field_type' => ModelAutocompleteType::class,
                'field_options' => [
                    'class' => Tag::class,
                    'property' => 'title',
                    'minimum_input_length' => 1,
                    'multiple' => true,
                ]
            ])
        ;
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('id', null, [
                'route' => ['name' => 'edit'],
            ])
            ->add('content', FieldDescriptionInterface::TYPE_HTML, [
                'truncate' => [
                    'length' => 150,
                ],
                'header_style' => 'width: 50%',
            ])
            ->add('tags')
            ->add('publishedAt', null, [
                'format' => 'Y-m-d H:i',
            ])
            ->add('isActive', null, [
                'editable' => true,
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
            ->add('content')
            ->add('publishedAt')
            ->add('isActive')
            ->add('tags')
        ;
    }

    protected function configureDefaultSortValues(array &$sortValues): void
    {
        $sortValues['_sort_by'] = 'publishedAt';
        $sortValues['_sort_order'] = 'DESC';
    }
}
