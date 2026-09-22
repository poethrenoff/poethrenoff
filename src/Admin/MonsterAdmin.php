<?php

namespace App\Admin;

use App\Entity\Monster;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;

/**
 * @extends AbstractAdmin<Monster>
 */
class MonsterAdmin extends AbstractAdmin
{
    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->clearExcept(['list']);
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('author')
            ->add('login')
            ->add('isActive')
        ;
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->add('place', null, [
                'label' => 'Место',
                'template' => 'admin/monster/place.html.twig',
            ])
            ->add('author', null, [
                'label' => 'Автор',
                'template' => 'admin/monster/author.html.twig',
            ])
            ->add('poems', null, [
                'label' => 'Стихов',
                'template' => 'admin/monster/poems.html.twig',
            ])
            ->add('lastVisitDate', null, [
                'label' => 'Дата',
                'template' => 'admin/monster/date.html.twig',
            ])
            ->add('isActive', null, [
                'label' => 'Активен',
            ])
        ;
    }

    protected function configureDefaultSortValues(array &$sortValues): void
    {
        $sortValues['_sort_by'] = 'place';
        $sortValues['_sort_order'] = 'ASC';
    }
}
