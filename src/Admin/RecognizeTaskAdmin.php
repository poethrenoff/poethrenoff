<?php

namespace App\Admin;

use App\Entity\RecognizeTask;
use App\Enum\RecognizeTaskStatus;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\FieldDescription\FieldDescriptionInterface;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\Show\ShowMapper;

/**
 * @extends AbstractAdmin<RecognizeTask>
 */
class RecognizeTaskAdmin extends AbstractAdmin
{
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->remove('create');
        $collection->remove('edit');
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('id')
            ->add('audio')
            ->add('status')
        ;
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('id')
            ->add('audio')
            ->add('status', FieldDescriptionInterface::TYPE_ENUM, [
                'choices' => RecognizeTaskStatus::cases(),
                'class' => RecognizeTaskStatus::class,
            ])
            ->add('createdAt')
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'show' => [],
                    'delete' => [],
                ],
                'header_style' => 'width: 150px',
            ])
        ;
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id')
            ->add('audio')
            ->add('status')
            ->add('resultText')
            ->add('stepData', null, [
                'template' => 'admin/recognize_task/step_data.html.twig',
            ])
            ->add('errorMessage')
            ->add('createdAt')
            ->add('updatedAt')
        ;
    }

    protected function configureDefaultSortValues(array &$sortValues): void
    {
        $sortValues['_sort_by'] = 'createdAt';
        $sortValues['_sort_order'] = 'DESC';
    }
}
