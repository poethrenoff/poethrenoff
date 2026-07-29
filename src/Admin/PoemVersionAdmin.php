<?php

namespace App\Admin;

use App\Entity\PoemVersion;
use App\Repository\PoemVersionRepository;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\AdminBundle\Route\RouteCollectionInterface;
use Sonata\DoctrineORMAdminBundle\Filter\DateTimeRangeFilter;
use Sonata\Form\Type\DateTimeRangePickerType;
use Symfony\Contracts\Service\Attribute\Required;

class PoemVersionAdmin extends AbstractAdmin
{
    private PoemVersionRepository $repository;

    #[Required]
    public function setRepository(PoemVersionRepository $repository): void
    {
        $this->repository = $repository;
    }

    public function getNextVersion(PoemVersion $version): ?PoemVersion
    {
        return $this->repository->findNextVersion($version);
    }

    protected function configureRoutes(RouteCollectionInterface $collection): void
    {
        $collection->remove('create');
        $collection->remove('edit');
    }

    protected function configureListFields(ListMapper $list): void
    {
        $list
            ->addIdentifier('id')
            ->add('title', null, [
                'header_style' => 'width: 70%',
            ])
            ->add('createdAt', null, [
                'format' => 'd.m.Y H:i',
            ])
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'show' => [],
                    'delete' => [],
                ],
                'header_style' => 'width: 150px',
            ])
        ;
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('id')
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

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id')
            ->add('createdAt', null, [
                'format' => 'd.m.Y H:i:s',
            ])
            ->add('poem')
            ->add('diff', null, [
                'template' => 'admin/poem_version/show_diff.html.twig',
            ])
        ;
    }

    protected function configureDefaultSortValues(array &$sortValues): void
    {
        $sortValues['_sort_by'] = 'createdAt';
        $sortValues['_sort_order'] = 'DESC';
    }
}
