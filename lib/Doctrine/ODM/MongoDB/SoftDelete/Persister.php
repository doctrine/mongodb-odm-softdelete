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
use Doctrine\ODM\MongoDB\Persisters\DocumentPersister;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata;
use Doctrine\MongoDB\Collection;
use MongoDate;

/**
 * The Persister class is responsible for persisting the queued deletions and restorations.
 * 
 * @license     http://www.opensource.org/licenses/lgpl-license.php LGPL
 * @link        www.doctrine-project.com
 * @since       1.0
 * @author      Jonathan H. Wage <jonwage@gmail.com>
 */
class Persister
{
    /**
     * ClassMetadata instance.
     *
     * @var Doctrine\MongoDB\ODM\Mapping\ClassMetadata $class
     */
    private $class;

    /**
     * Database Collection instance.
     *
     * @var Doctrine\MongoDB\Collection $collection
     */
    private $collection;

    /**
     * DocumentPersister instance this SoftDelete persister wraps.
     *
     * @var Doctrine\ODM\MongoDB\Persisters\DocumentPersister
     */
    private $persister;

    /**
     * Array of queued documents to be deleted.
     *
     * @var array
     */
    private $queuedDeletes = array();

    /**
     * Array of custom criteria to delete by.
     *
     * @var array
     */
    private $deleteBy = array();

    /**
     * Array of queued documents to be restored.
     *
     * @var array
     */
    private $queuedRestores = array();

    /**
     * Array of custom criteria to restore by.
     *
     * @var array
     */
    private $restoreBy = array();

    /**
     * Constructs a new Persister instance
     *
     * @param Configuration $configuration
     * @param ClassMetadata $class
     * @param Collection $collection
     */
    public function __construct(Configuration $configuration, ClassMetadata $class, Collection $collection, DocumentPersister $persister)
    {
        $this->configuration = $configuration;
        $this->class = $class;
        $this->collection = $collection;
        $this->persister = $persister;
    }

    /**
     * Gets the ClassMetadata instance.
     *
     * @return ClassMetadata $class
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * Gets the database Collection instance.
     *
     * @return Collection $collection
     */
    public function getCollection()
    {
        return $this->collection;
    }

    /**
     * Add a SoftDeleteable document to the queued deletes.
     *
     * @param SoftDeleteable $document
     */
    public function addDelete(SoftDeleteable $document)
    {
        $this->queuedDeletes[spl_object_hash($document)] = $document;
    }

    /**
     * Gets the array of SoftDeleteable documents queued for deletion.
     *
     * @return array $queuedDeletes
     */
    public function getDeletes()
    {
        return $this->queuedDeletes;
    }

    /**
     * Add an array of criteria to delete by.
     *
     * @param array $criteria
     */
    public function addDeleteBy(array $criteria)
    {
        $this->deleteBy[] = $criteria;
    }

    /**
     * Gets the array of criteria to delete by.
     *
     * @return array $criteria
     */
    public function getDeleteBy()
    {
        return $this->deleteBy;
    }

    /**
     * Add a SoftDeleteable document to the queued restores.
     *
     * @param SoftDeleteable $document
     */
    public function addRestore(SoftDeleteable $document)
    {
        $this->queuedRestores[spl_object_hash($document)] = $document;
    }

    /**
     * Gets the array of SoftDeleteable documents queued for restoration.
     *
     * @return array $queuedRestores
     */
    public function getRestores()
    {
        return $this->queuedRestores;
    }

    /**
     * Add an array of criteria to restore by.
     *
     * @param array $criteria
     */
    public function addRestoreBy(array $criteria)
    {
        $this->restoreBy[] = $criteria;
    }

    /**
     * Gets the array of criteria to restore by.
     *
     * @return array $criteria
     */
    public function getRestoreBy()
    {
        return $this->restoreBy;
    }

    /**
     * Executes the queued deletes.
     *
     * @param MongoDate $date Date to the deleted field to. Mainly for testing.
     */
    public function executeDeletes(MongoDate $date = null)
    {
        $ids = array();
        foreach ($this->queuedDeletes as $document) {
            $ids[] = $this->class->getIdentifierObject($document);
        }

        $query = array(
            '_id' => array(
                '$in' => $ids
            )
        );
        $this->deleteQuery($query, array(), $date);
        foreach ($this->deleteBy as $deleteBy) {
            list($criteria, $flags) = $deleteBy;
            $this->deleteQuery($criteria, $flags, $date);
        }
        $this->deleteBy = array();
        $this->queuedDeletes = array();
    }

    /**
     * Executes the queued restores.
     */
    public function executeRestores()
    {
        $ids = array();
        foreach ($this->queuedRestores as $document) {
            $ids[] = $this->class->getIdentifierObject($document);
        }

        $query = array(
            '_id' => array(
                '$in' => $ids
            )
        );
        $this->restoreQuery($query, array());
        foreach ($this->restoreBy as $restoreBy) {
            list($criteria, $flags) = $restoreBy;
            $this->restoreQuery($criteria, $flags);
        }
        $this->restoreBy = array();
        $this->queuedRestores = array();
    }

    private function deleteQuery(array $query, array $flags, MongoDate $date = null)
    {
        $deletedFieldName = $this->configuration->getDeletedFieldName();
        $newObj = array(
            '$set' => array(
                $deletedFieldName => $date ? $date : new MongoDate()
            )
        );
        foreach ($flags as $fieldName => $value) {
            $newObj['$set'][$fieldName] = $value;
        }
        $query[$deletedFieldName] = array('$exists' => false);
        return $this->query($query, $newObj);
    }

    private function restoreQuery(array $query, array $flags)
    {
        $deletedFieldName = $this->configuration->getDeletedFieldName();
        $newObj = array(
            '$unset' => array(
                $deletedFieldName => true
            )
        );
        foreach ($flags as $fieldName => $value) {
            $newObj['$unset'][$fieldName] = true;
            $query[$fieldName] = $value;
        }
        $query[$deletedFieldName] = array('$exists' => true);
        return $this->query($query, $newObj);
    }

    private function query(array $query, array $newObj)
    {
        $query = $this->persister->prepareQuery($query);
        $result = $this->collection->update($query, $newObj, array(
            'multiple' => true,
            'safe' => true
        ));
        return $result;
    }
}