<?php

/*
 * This file is part of the Thelia package.
 * http://www.thelia.net
 *
 * (c) OpenStudio <info@thelia.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Carousel\Model;

use Carousel\Model\Base\Carousel as BaseCarousel;
use Propel\Runtime\ActiveQuery\ModelCriteria;
use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Thelia\Core\File\FileModelInterface;
use Thelia\Core\File\FileModelParentInterface;

class Carousel extends BaseCarousel implements FileModelInterface, FileModelParentInterface
{
    /**
     * Migration Thelia 3 : FileModelInterface::getFile() est desormais typee
     * `string`, alors que la colonne `file` est nullable — Propel genere donc
     * `?string` dans la classe de base et l'implementation devient incompatible.
     * On restreint le type de retour ici (covariance legale) plutot que de rendre
     * la colonne obligatoire, ce qui modifierait le schema de la table.
     */
    public function getFile(): string
    {
        return (string) parent::getFile();
    }

    public function preDelete(?ConnectionInterface $con = null): bool {
        $uploadDir = $this->getUploadDir();
        $fs = new Filesystem();

        try {
            foreach ([parent::getFile(), $this->getMobileFile()] as $file) {
                if ($file !== null && $file !== '') {
                    $fs->remove($uploadDir.DS.$file);
                }
            }

            return true;
        } catch (IOException $e) {
            return false;
        }
    }

    /**
     * Set file parent id.
     *
     * @param int $parentId parent id
     *
     * @return $this
     */
    public function setParentId($parentId): static {
        return $this;
    }

    /**
     * Get file parent id.
     *
     * @return int parent id
     */
    public function getParentId(): int {
        return $this->getId();
    }

    public function getParentFileModel(): FileModelParentInterface {
        return new self();
    }

    public function getUpdateFormId(): string {
        return 'carousel.image';
    }

    /**
     * @return string the path to the upload directory where files are stored, without final slash
     */
    public function getUploadDir(): string {
        $carousel = new \Carousel\Carousel();

        return $carousel->getUploadDir();
    }

    /**
     * @return string the URL to redirect to after update from the back-office
     */
    public function getRedirectionUrl(): string {
        return '/admin/module/Carousel';
    }

    /**
     * Get the Query instance for this object.
     *
     * @return ModelCriteria
     */
    public function getQueryInstance(): \Propel\Runtime\ActiveQuery\ModelCriteria {
        return CarouselQuery::create();
    }

    // Migration Thelia 3 : le parametre est desormais type dans l'interface
    // (?int, car la colonne est un TINYINT nullable et non un booleen).
    // La visibilite est portee par la colonne `disable` (semantique inversee) :
    // un no-op silencieux piegerait tout appelant generique de FileModelInterface.
    public function setVisible(?int $visible = null): static
    {
        return $this->setDisable($visible ? 0 : 1);
    }
}
