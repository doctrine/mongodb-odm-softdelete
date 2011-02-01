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
     * Array of queued documents to be deleted.
     *
     * @var array
     */
    private $queuedDeletes = array();

    /**
     * Array of queued documents to be restored.
     *
     * @var array
     */
    private $queuedRestores = array();

    /**
     * Constructs a new Persister instance
     *
     * @param Configuration $configuration
     * @param ClassMetadata $class
     * @param Collection $collection
     */
    public function __construct(Configuration $configuration, ClassMetadata $class, Collection $collection)
    {
        $this->configuration = $configuration;
        $this->class = $class;
        $this->collection = $collection;
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
        $newObj = array(
            '$set' => array(
                $this->configuration->getDeletedFieldName() => $date ? $date : new MongoDate()
            )
        );

        $this->collection->update($query, $newObj, array(
            'multiple' => true,
            'safe' => true
        ));

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
        $newObj = array(
            '$unset' => array(
                $this->configuration->getDeletedFieldName() => true
            )
        );
        $this->collection->update($query, $newObj, array(
            'multiple' => true,
            'safe' => true
        ));

        $this->queuedRestores = array();
    }
}