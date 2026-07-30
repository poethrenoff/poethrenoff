<?php

namespace App\Admin;

use App\Entity\Work;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\FieldDescription\FieldDescriptionInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\ModelAutocompleteType;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\DoctrineORMAdminBundle\Filter\ChoiceFilter;
use Sonata\DoctrineORMAdminBundle\Filter\DateTimeRangeFilter;
use Sonata\Form\Type\DateTimeRangePickerType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class WorkVoteAdmin extends AbstractAdmin
{
    protected function configureFormFields(FormMapper $form): void
    {
        $form
            ->add('work', ModelAutocompleteType::class, [
                'class' => Work::class,
                'property' => 'title',
                'minimum_input_length' => 1,
                'btn_add' => false,
            ])
            ->add('ipHash', TextType::class)
            ->add('userAgentHash', TextType::class)
            ->add('voteType', ChoiceType::class, [
                'choices' => [
                    '👍' => 'like',
                    '👎' => 'dislike',
                ],
            ])
            ->add('createdAt', DateTimeType::class)
        ;
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('work')
            ->add('voteType', ChoiceFilter::class, [
                'field_type' => ChoiceType::class,
                'field_options' => [
                    'choices' => [
                        '👍' => 'like',
                        '👎' => 'dislike',
                    ]
                ]
            ])
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
        ;
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('id', null, [
                'route' => ['name' => 'edit'],
            ])
            ->add('work', null, [
                'header_style' => 'width: 50%',
            ])
            ->add('voteType', FieldDescriptionInterface::TYPE_CHOICE, [
                'choices' => [
                    'like' => '👍',
                    'dislike' => '👎',
                ],
            ])
            ->add('createdAt')
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
            ->add('work')
            ->add('ipHash')
            ->add('userAgentHash')
            ->add('voteType')
            ->add('createdAt')
        ;
    }

    protected function configureDefaultSortValues(array &$sortValues): void
    {
        $sortValues['_sort_by'] = 'createdAt';
        $sortValues['_sort_order'] = 'DESC';
    }
}
