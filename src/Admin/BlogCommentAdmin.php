<?php

namespace App\Admin;

use App\Entity\BlogComment;
use App\Entity\BlogPost;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\FieldDescription\FieldDescriptionInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\DoctrineORMAdminBundle\Filter\DateTimeRangeFilter;
use Sonata\Form\Type\DateTimeRangePickerType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

/**
 * @extends AbstractAdmin<BlogComment>
 */
class BlogCommentAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('post', ModelAutocompleteType::class, [
                'class' => BlogPost::class,
                'property' => 'id',
                'minimum_input_length' => 1,
                'btn_add' => false,
            ])
            ->add('parent', ModelAutocompleteType::class, [
                'class' => BlogComment::class,
                'property' => 'id',
                'minimum_input_length' => 1,
                'required' => false,
                'btn_add' => false,
            ])
            ->add('author', TextType::class)
            ->add('content', null, [
                'attr' => ['class' => 'trumbowyg-editor'],
            ])
            ->add('info', TextType::class, [
                'required' => false,
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
            ->add('author')
            ->add(
                'createdAt',
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
            ->add('author')
            ->add('createdAt', null, [
                'format' => 'Y-m-d H:i',
            ])
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
            ->add('parent')
            ->add('author')
            ->add('content')
            ->add('info')
            ->add('createdAt')
            ->add('isActive')
        ;
    }

    protected function configureDefaultSortValues(array &$sortValues): void
    {
        $sortValues['_sort_by'] = 'createdAt';
        $sortValues['_sort_order'] = 'DESC';
    }
}
