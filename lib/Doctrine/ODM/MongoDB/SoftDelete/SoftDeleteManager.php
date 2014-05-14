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
use Doctrine\ODM\MongoDB\Query\Builder;
use Doctrine\Common\EventManager;
use DateTime;
use MongoDate;

/**
 * The SoftDeleteManager class is responsible for managing the deleted state of a SoftDeleteable instance.
 *
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 */
class SoftDeleteManager
{
    /**
     * The DocumentManager instance.
     *
     * @var DocumentManager $dm
     */
    private $dm;

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
     * Array of special criteria to delete by.
     *
     * @var array
     */
    private $deleteBy = array();

    /**
     * Array of special criteria to restore by.
     *
     * @var array
     */
    private $restoreBy = array();

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
    public function __construct(DocumentManager $dm, Configuration $configuration, EventManager $eventManager)
    {
        $this->dm = $dm;
        $this->configuration = $configuration;
        $this->eventManager = $eventManager;
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
     * Gets the Configuration instance.
     *
     * @return Configuration $configuration
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * Gets the EventManager instance/
     *
     * @return EventManager $eventManager
     */
    public function getEventManager()
    {
        return $this->eventManager;
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
        $persister = $this->dm->getUnitOfWork()->getDocumentPersister($className);
        $this->persisters[$className] = new Persister($this->configuration, $class, $collection, $persister);
        return $this->persisters[$className];
    }

    /**
     * Schedule some special criteria to delete some documents by.
     *
     * @param string $className The class name to delete by.
     * @param array $criteria The array of criteria to delete from the classes collection.
     * @param array $flags The array of flags to set on the deleted documents which distinguish this delete.
     */
    public function deleteBy($className, array $criteria, array $flags = array())
    {
        $this->deleteBy[$className][] = array($criteria, $flags);
    }

    /**
     * Schedule some special criteria to restore some documents by.
     *
     * @param string $className The class name to restore by.
     * @param array $criteria The array of criteria to restore from the classes collection.
     * @param array $flags The array of flags to limit the restored documents to.
     */
    public function restoreBy($className, array $criteria, array $flags = array())
    {
        $this->restoreBy[$className][] = array($criteria, $flags);
    }

    /**
     * Schedules a SoftDeleteable document instance for deletion on next flush.
     *
     * @param SoftDeleteable $document
     */
    public function delete(SoftDeleteable $document)
    {
        $oid = spl_object_hash($document);
        if (isset($this->documentDeletes[$oid])) {
            return;
        }

        // If scheduled for restore then remove it
        unset($this->documentRestores[$oid]);

        if ($this->eventManager->hasListeners(Events::preSoftDelete)) {
            $this->eventManager->dispatchEvent(Events::preSoftDelete, new Event\LifecycleEventArgs($document, $this));
        }

        $this->documentDeletes[$oid] = $document;
    }

    /**
     * Schedules a SoftDeleteable document instance for restoration on next flush.
     *
     * @param SoftDeleteable $document
     */
    public function restore(SoftDeleteable $document)
    {
        $oid = spl_object_hash($document);
        if (isset($this->documentRestores[$oid])) {
            return;
        }

        // If scheduled for delete then remove it
        unset($this->documentDeletes[$oid]);

        if ($this->eventManager->hasListeners(Events::preRestore)) {
            $this->eventManager->dispatchEvent(Events::preRestore, new Event\LifecycleEventArgs($document, $this));
        }

        $this->documentRestores[$oid] = $document;
    }

    /**
     * Commits all the scheduled deletions and restorations to the database.
     */
    public function flush()
    {
        // document deletes
        if ($this->documentDeletes || $this->deleteBy) {
            $this->executeDeletes();
        }

        // document restores
        if ($this->documentRestores || $this->restoreBy) {
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
        $dateTime = new DateTime();
        $mongoDate = new MongoDate($dateTime->getTimestamp());

        $deletedFieldName = $this->configuration->getDeletedFieldName();

        $persisters = array();
        foreach ($this->deleteBy as $className => $criterias) {
            $persister = $this->getDocumentPersister($className);
            $persisters[$className] = $persister;
            foreach ($criterias as $criteria) {
                $persister->addDeleteBy($criteria);
            }
        }
        $documentDeletes = array();
        foreach ($this->documentDeletes as $document) {
            $className = get_class($document);
            $documentDeletes[$className][] = $document;
            $persister = $this->getDocumentPersister($className);
            $persisters[$className] = $persister;
            $persister->addDelete($document);
        }
        foreach ($persisters as $className => $persister) {
            $persister->executeDeletes($mongoDate);

            $class = $this->dm->getClassMetadata($className);

            if (isset($documentDeletes[$className])) {
                $documents = $documentDeletes[$className];
                foreach ($documents as $document) {
                    $class->setFieldValue($document, $deletedFieldName, $dateTime);

                    if ($this->eventManager->hasListeners(Events::postSoftDelete)) {
                        $this->eventManager->dispatchEvent(Events::postSoftDelete, new Event\LifecycleEventArgs($document, $this));
                    }
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

        $persisters = array();
        foreach ($this->restoreBy as $className => $criterias) {
            $persister = $this->getDocumentPersister($className);
            $persisters[$className] = $persister;
            foreach ($criterias as $criteria) {
                $persister->addRestoreBy($criteria);
            }
        }
        $documentRestores = array();
        foreach ($this->documentRestores as $document) {
            $className = get_class($document);
            $documentRestores[$className][] = $document;
            $persister = $this->getDocumentPersister($className);
            $persisters[$className] = $persister;
            $persister->addRestore($document);
        }
        foreach ($persisters as $className => $persister) {
            $persister->executeRestores();

            $class = $this->dm->getClassMetadata($className);

            if (isset($documentRestores[$className])) {
                $documents = $documentRestores[$className];
                foreach ($documents as $document) {
                    $class->setFieldValue($document, $deletedFieldName, null);

                    if ($this->eventManager->hasListeners(Events::postRestore)) {
                        $this->eventManager->dispatchEvent(Events::postRestore, new Event\LifecycleEventArgs($document, $this));
                    }
                }
            }
        }
    }
}
