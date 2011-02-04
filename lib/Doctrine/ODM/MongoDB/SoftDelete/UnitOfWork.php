<?php
/*
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
 * "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
 * LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR
 * A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT
 * OWNER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL,
 * SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT
 * LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE,
 * DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY
 * THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This software consists of voluntary contributions made by many individuals
 * and is licensed under the LGPL. For more information, see
 * <http://www.doctrine-project.org>.
 */

namespace Doctrine\ODM\MongoDB\SoftDelete;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\SoftDelete\Configuration;
use Doctrine\Common\EventManager;
use InvalidArgumentException;
use DateTime;

/**
 * UnitOfWork is responsible for tracking the deleted state of objects and giving you the ability
 * to queue deletions and restorations to be committed.
 * 
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 */
class UnitOfWork
{
    /**
     * The DocumentManager instance.
     *
     * @var DocumentManager $dm
     */
    private $dm;

    /**
     * The SoftDeleteManager instance.
     *
     * @var string
     */
    private $sdm;

    /**
     * The SoftDelete configuration instance/
     *
     * @var Configuration $configuration
     */
    private $configuration;

    /**
     * The EventManager instance used for managing events.
     *
     * @var EventManager $eventManager
     */
    private $eventManager;

    /**
     * Array of scheduled document deletes.
     *
     * @var array
     */
    private $documentDeletes = array();

    /**
     * Array of scheduled document restores.
     *
     * @var array
     */
    private $documentRestores = array();

    /**
     * Array of lazily instantiated soft delete document persisters.
     *
     * @var string
     */
    private $persisters = array();

    /**
     * Constructs a new UnitOfWork instance.
     *
     * @param DocumentManager $dm
     * @param Configuration $configuration
     */
    public function __construct(DocumentManager $dm, Configuration $configuration)
    {
        $this->dm = $dm;
        $this->configuration = $configuration;
    }

    /**
     * Sets the SoftDeleteManager this UnitOfWork belongs to.
     *
     * @param SoftDeleteManager $sdm
     */
    public function setSoftDeleteManager(SoftDeleteManager $sdm)
    {
        $this->sdm = $sdm;
    }

    /**
     * Gets the SoftDeleteManager this UnitOfWork belongs to.
     *
     * @return SoftDeleteManager $sdm
     */
    public function getSoftDeleteManager()
    {
        return $this->sdm;
    }

    /**
     * Sets the UnitOfWork event manager.
     *
     * @param EventManager $eventManager
     */
    public function setEventManager(EventManager $eventManager)
    {
        $this->eventManager = $eventManager;
    }

    /**
     * Gets the UnitOfWork event manager.
     *
     * @return EventManager $eventManager
     */
    public function getEventManager()
    {
        return $this->eventManager;
    }

    /**
     * Gets the DocumentManager instance
     *
     * @return DocumentManager $dm
     */
    public function getDocumentManager()
    {
        return $this->dm;
    }

    /**
     * Gets the array of scheduled document deletes.
     *
     * @return array $documentDeletes
     */
    public function getDocumentDeletes()
    {
        return $this->documentDeletes;
    }

    /**
     * Gets the array of scheduled document restores.
     *
     * @return array $documentRestores
     */
    public function getDocumentRestores()
    {
        return $this->documentRestores;
    }

    /**
     * Checks if a given SoftDeleteable document instance is currently scheduled for delete.
     *
     * @param SoftDeleteable $document
     */
    public function isScheduledForDelete(SoftDeleteable $document)
    {
        return isset($this->documentDeletes[spl_object_hash($document)]) ? true : false;
    }

    /**
     * Checks if a given SoftDeleteable document instance is currently scheduled for restore.
     *
     * @param SoftDeleteable $document
     */
    public function isScheduledForRestore(SoftDeleteable $document)
    {
        return isset($this->documentRestores[spl_object_hash($document)]) ? true : false;
    }

    /**
     * Gets or creates a Persister instance for the given class name.
     *
     * @param string $className
     * @return Persister $persister
     */
    public function getDocumentPersister($className)
    {
        if (isset($this->persisters[$className])) {
            return $this->persisters[$className];
        }
        $class = $this->dm->getClassMetadata($className);
        $collection = $this->dm->getDocumentCollection($className);
        $this->persisters[$className] = new Persister($this->configuration, $class, $collection);
        return $this->persisters[$className];
    }

    /**
     * Schedules a SoftDeleteable document instance for deletion on next flush.
     *
     * @param SoftDeleteable $document
     * @throws InvalidArgumentException
     */
    public function delete(SoftDeleteable $document)
    {
        $oid = spl_object_hash($document);
        if (isset($this->documentDeletes[$oid])) {
            throw new InvalidArgumentException('Document is already scheduled for delete.');
        }

        // If scheduled for restore then remove it
        unset($this->documentRestores[$oid]);

        $this->documentDeletes[$oid] = $document;
    }

    /**
     * Schedules a SoftDeleteable document instance for restoration on next flush.
     *
     * @param SoftDeleteable $document
     * @throws InvalidArgumentException
     */
    public function restore(SoftDeleteable $document)
    {
        $oid = spl_object_hash($document);
        if (isset($this->documentRestores[$oid])) {
            throw new InvalidArgumentException('Document is already scheduled for restore.');
        }

        // If scheduled for delete then remove it
        unset($this->documentDeletes[$oid]);

        $this->documentRestores[$oid] = $document;
    }

    /**
     * Commits all the scheduled deletions and restorations to the database.
     */
    public function commit()
    {
        // document deletes
        if ($this->documentDeletes) {
            $this->executeDeletes();
        }

        // document restores
        if ($this->documentRestores) {
            $this->executeRestores();
        }
    }

    /**
     * Clears the UnitOfWork and forgets any currently scheduled deletions or restorations.
     */
    public function clear()
    {
        $this->documentDeletes = array();
        $this->documentRestores = array();
    }

    /**
     * Executes the queued deletions.
     */
    private function executeDeletes()
    {
        $deletedFieldName = $this->configuration->getDeletedFieldName();

        $documentDeletes = array();
        foreach ($this->documentDeletes as $document) {
            $className = get_class($document);
            $documentDeletes[$className][] = $document;
        }
        foreach ($documentDeletes as $className => $documents) {
            $persister = $this->getDocumentPersister($className);
            foreach ($documents as $document) {

                if ($this->eventManager->hasListeners(Events::preSoftDelete)) {
                    $this->eventManager->dispatchEvent(Events::preSoftDelete, new Event\LifecycleEventArgs($document, $this->sdm));
                }

                $persister->addDelete($document);
            }
            $persister->executeDeletes();

            $class = $this->dm->getClassMetadata($className);

            $date = new DateTime();
            foreach ($documents as $document) {
                $class->setFieldValue($document, $deletedFieldName, $date);

                if ($this->eventManager->hasListeners(Events::postSoftDelete)) {
                    $this->eventManager->dispatchEvent(Events::postSoftDelete, new Event\LifecycleEventArgs($document, $this->sdm));
                }
            }
        }
    }

    /**
     * Executes the queued restorations.
     */
    private function executeRestores()
    {
        $deletedFieldName = $this->configuration->getDeletedFieldName();

        $documentRestores = array();
        foreach ($this->documentRestores as $document) {
            $className = get_class($document);
            $documentRestores[$className][] = $document;
        }
        foreach ($documentRestores as $className => $documents) {
            $persister = $this->getDocumentPersister($className);
            foreach ($documents as $document) {

                if ($this->eventManager->hasListeners(Events::preSoftDeleteRestore)) {
                    $this->eventManager->dispatchEvent(Events::preSoftDeleteRestore, new Event\LifecycleEventArgs($document, $this->sdm));
                }

                $persister->addRestore($document);
            }
            $persister->executeRestores();
    
            $class = $this->dm->getClassMetadata($className);
    
            foreach ($documents as $document) {
                $class->setFieldValue($document, $deletedFieldName, null);

                if ($this->eventManager->hasListeners(Events::postSoftDeleteRestore)) {
                    $this->eventManager->dispatchEvent(Events::postSoftDeleteRestore, new Event\LifecycleEventArgs($document, $this->sdm));
                }
            }
        }
    }
}