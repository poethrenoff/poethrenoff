<?php

namespace App\Admin;

use App\Entity\Picture;
use Doctrine\ORM\EntityManagerInterface;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\Attribute\Required;

class PictureAdmin extends AbstractAdmin
{
    private string $projectDir;
    private EntityManagerInterface $entityManager;

    #[Required]
    public function setProjectDir(#[Autowire('%kernel.project_dir%')] string $projectDir): void
    {
        $this->projectDir = $projectDir;
    }

    #[Required]
    public function setEntityManager(EntityManagerInterface $entityManager): void
    {
        $this->entityManager = $entityManager;
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $isNew = $this->isNew();

        $form
            ->add('title')
            ->add('imagePath', FileType::class, [
                'required' => $isNew,
                'help' => $this->getSubject() ? $this->getSubject()->getImagePath() : '',
                'mapped' => false,
            ])
            ->add('sourcePath', FileType::class, [
                'required' => $isNew,
                'help' => $this->getSubject() ? $this->getSubject()->getSourcePath() : '',
                'mapped' => false,
            ])
            ->add('date', DateType::class)
        ;

        if (!$isNew) {
            $form
                ->add('position', NumberType::class, [
                    'required' => $isNew,
                ])
            ;
        }

        $form
            ->add('isActive', CheckboxType::class, [
                'required' => false,
            ])
        ;
    }

    public function prePersist(object $object): void
    {
        $this->manageFileUpload($object);

        if ($this->isNew() && ($object->getPosition() === null || $object->getPosition() === 0.0)) {
            $maxPosition = $this->getMaxPositionForDate($object->getDate());
            $object->setPosition($maxPosition + 1);
        }
    }

    public function preUpdate(object $object): void
    {
        $this->manageFileUpload($object);
    }

    private function isNew(): bool
    {
        $subject = $this->getSubject();
        return $subject->getId() === null;
    }

    private function manageFileUpload(object $object): void
    {
        if (!$object instanceof Picture) {
            return;
        }

        $this->processFileUpload($object, 'imagePath');
        $this->processFileUpload($object, 'sourcePath');
    }

    private function processFileUpload(Picture $picture, string $property): void
    {
        if (!$this->getForm()->has($property)) {
            return;
        }

        $file = $this->getForm()->get($property)->getData();
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return;
        }

        $slugger = new AsciiSlugger();
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename)->lower();
        $extension = $file->guessExtension();

        $newFilename = sprintf('%s.%s', $safeFilename, $extension);

        $relativePath = $this->generatePath($picture, $newFilename);
        $absolutePath = $this->getUploadRootDir() . $relativePath;

        $file->move(dirname($absolutePath), $newFilename);

        match ($property) {
            'imagePath' => $picture->setImagePath($relativePath),
            'sourcePath' => $picture->setSourcePath($relativePath),
        };
    }

    private function generatePath(Picture $picture, string $filename): string
    {
        $date = $picture->getDate();
        $path = sprintf(
            '/upload/image/%s/%s/%s',
            $date->format('Y'),
            $date->format('m'),
            $date->format('d')
        );

        $fullPath = $this->getUploadRootDir() . $path;
        if (!is_dir($fullPath) && !mkdir($fullPath, 0755, true) && !is_dir($fullPath)) {
            throw new \RuntimeException(sprintf('Не удалось создать директорию "%s"', $fullPath));
        }

        return $path . '/' . $filename;
    }

    private function getUploadRootDir(): string
    {
        return $this->projectDir . '/htdocs/' . $this->getRequest()->server->get('APP_SITE_CONTEXT');
    }

    private function getMaxPositionForDate(\DateTimeInterface $date): float
    {
        $qb = $this->entityManager->createQueryBuilder();

        return (float) $qb
            ->select('MAX(p.position)')
            ->from(Picture::class, 'p')
            ->where("p.date = :date")
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult() ?? 0.0;
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('title')
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
                'header_style' => 'width: 50%',
            ])
            ->add('date', null, [
                'format' => 'Y-m-d',
            ])
            ->add('position', null, [
                'editable' => true,
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
            ->add('title')
            ->add('imagePath')
            ->add('sourcePath')
            ->add('date')
            ->add('position')
            ->add('isActive')
        ;
    }

    protected function configureDefaultSortValues(array &$sortValues): void
    {
        $sortValues['_sort_by'] = 'id';
        $sortValues['_sort_order'] = 'DESC';
    }
}
