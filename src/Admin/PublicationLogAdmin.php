<?php

namespace App\Admin;

use App\Entity\PublicationLog;
use App\Enum\PublicationStatus;
use App\Enum\PublishPlatform;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\FieldDescription\FieldDescriptionInterface;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\AdminBundle\Show\ShowMapper;

/**
 * @extends AbstractAdmin<PublicationLog>
 */
class PublicationLogAdmin extends AbstractAdmin
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
            ->add('poem')
            ->add('platform')
            ->add('status')
        ;
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('id')
            ->add('poem')
            ->add('platform', FieldDescriptionInterface::TYPE_ENUM, [
                'choices' => PublishPlatform::cases(),
                'class' => PublishPlatform::class,
            ])
            ->add('status', FieldDescriptionInterface::TYPE_ENUM, [
                'choices' => PublicationStatus::cases(),
                'class' => PublicationStatus::class,
            ])
            ->add('externalUrl', FieldDescriptionInterface::TYPE_URL)
            ->add('publishedAt')
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
            ->add('poem')
            ->add('platform')
            ->add('status')
            ->add('externalPostId')
            ->add('externalUrl', FieldDescriptionInterface::TYPE_URL)
            ->add('errorMessage')
            ->add('publishedAt')
        ;
    }

    protected function configureDefaultSortValues(array &$sortValues): void
    {
        $sortValues['_sort_by'] = 'publishedAt';
        $sortValues['_sort_order'] = 'DESC';
    }
}
