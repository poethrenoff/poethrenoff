<?php

namespace App\Admin;

use App\Entity\Audio;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Show\ShowMapper;
use Sonata\DoctrineORMAdminBundle\Filter\DateTimeRangeFilter;
use Sonata\Form\Type\DateTimeRangePickerType;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Contracts\Service\Attribute\Required;

/**
 * @extends AbstractAdmin<Audio>
 */
class AudioAdmin extends AbstractAdmin
{
    private string $projectDir;
    private string $siteContext;

    #[Required]
    public function setProjectDir(#[Autowire('%kernel.project_dir%')] string $projectDir): void
    {
        $this->projectDir = $projectDir;
    }

    #[Required]
    public function setSiteContext(#[Autowire('%app.site_context%')] string $siteContext): void
    {
        $this->siteContext = $siteContext;
    }

    protected function configureFormFields(FormMapper $form): void
    {
        $isNew = $this->isNew();
        $subject = $this->getSubject();

        $form
            ->add('title', TextType::class)
            ->add('filePath', FileType::class, [
                'required' => $isNew,
                'help' => $subject->getFilePath(),
                'mapped' => false,
            ])
            ->add('duration', IntegerType::class, ['required' => false])
            ->add('createdAt', DateTimeType::class)
        ;
    }

    public function prePersist(object $object): void
    {
        $this->manageFileUpload($object);
    }

    public function preUpdate(object $object): void
    {
        $this->manageFileUpload($object);
    }

    private function isNew(): bool
    {
        return $this->getSubject()->getId() === null;
    }

    private function manageFileUpload(object $object): void
    {
        if (!$object instanceof Audio) {
            return;
        }

        $this->processFileUpload($object);
    }

    private function processFileUpload(Audio $audio): void
    {
        $file = $this->getForm()->get('filePath')->getData();
        if (!$file instanceof UploadedFile || !$file->isValid()) {
            return;
        }

        $slugger = new AsciiSlugger();
        $originalFilename = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $safeFilename = $slugger->slug($originalFilename)->lower();
        $extension = $file->guessExtension();

        $newFilename = sprintf('%s.%s', $safeFilename, $extension);

        $relativePath = $this->generatePath($newFilename);
        $absolutePath = $this->getUploadRootDir() . $relativePath;

        $file->move(dirname($absolutePath), $newFilename);

        $audio->setFilePath($relativePath);
    }

    private function generatePath(string $filename): string
    {
        $path = '/upload/audio';
        $fullPath = $this->getUploadRootDir() . $path;
        if (!is_dir($fullPath) && !mkdir($fullPath, 0755, true) && !is_dir($fullPath)) {
            throw new \RuntimeException(sprintf('Не удалось создать директорию "%s"', $fullPath));
        }

        return $path . '/' . $filename;
    }

    private function getUploadRootDir(): string
    {
        return $this->projectDir . '/htdocs/' . $this->siteContext;
    }

    protected function configureDatagridFilters(DatagridMapper $filter): void
    {
        $filter
            ->add('title')
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
            ->add('title', null, [
                'header_style' => 'width: 50%',
            ])
            ->add('duration')
            ->add('createdAt', null, [
                'format' => 'Y-m-d H:i',
            ])
            ->add(ListMapper::NAME_ACTIONS, null, [
                'actions' => [
                    'listen' => [
                        'template' => 'admin/audio/play.html.twig',
                    ],
                    'show' => [],
                    'edit' => [],
                    'delete' => [],
                ],
                'header_style' => 'width: 265px',
            ])
        ;
    }

    protected function configureShowFields(ShowMapper $show): void
    {
        $show
            ->add('id')
            ->add('title')
            ->add('filePath')
            ->add('duration')
            ->add('createdAt')
        ;
    }

    protected function configureDefaultSortValues(array &$sortValues): void
    {
        $sortValues['_sort_by'] = 'createdAt';
        $sortValues['_sort_order'] = 'DESC';
    }
}
