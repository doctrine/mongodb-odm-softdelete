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
use Doctrine\Common\EventManager;

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
     * DocumentManager instance this object wraps.
     *
     * @var DocumentManager $dm
     */
    private $dm;

    /**
     * The SoftDelete Configuration instance.
     *
     * @var Configuration $config
     */
    private $config;

    /**
     * The SoftDelete UnitOfWork instance.
     *
     * @var UnitOfWork $unitOfWork
     */
    private $unitOfWork;

    /**
     * The EventManager instance used for managing events.
     *
     * @var EventManager $eventManager
     */
    private $eventManager;

    /**
     * Constructs a new SoftDeleteManager instance.
     *
     * @param DocumentManager $dm
     * @param Configuration $configuration
     * @param UnitOfWork $unitOfWork
     */
    public function __construct(DocumentManager $dm, Configuration $configuration, UnitOfWork $unitOfWork, EventManager $eventManager = null)
    {
        $this->dm = $dm;
        $this->config = $configuration;
        $this->unitOfWork = $unitOfWork;
        $this->eventManager = $eventManager ?: new EventManager();
        $this->unitOfWork->setEventManager($this->eventManager);
        $this->unitOfWork->setSoftDeleteManager($this);
    }

    /**
     * Gets the DocumentManager
     *
     * @return DocumentManager $dm
     */
    public function getDocumentManager()
    {
        return $this->dm;
    }

    /**
     * Gets the Configuration
     *
     * @return Configuration $config
     */
    public function getConfiguration()
    {
        return $this->config;
    }

    /**
     * Gets the UnitOfWork
     *
     * @return UnitOfWork $unitOfWork
     */
    public function getUnitOfWork()
    {
        return $this->unitOfWork;
    }

    /**
     * Gets the EventManager
     *
     * @return EventManager $eventManager
     */
    public function getEventManager()
    {
        return $this->eventManager;
    }

    /**
     * Creates a new query builder instance that will automatically exclude deleted documents
     * by adding a { deletedAt : { $exists : false } } condition.
     *
     * @param string $documentName The document class name to create the query builder for.
     * @return Doctrine\MongoDB\ODM\Query\Builder $qb
     */
    public function createQueryBuilder($documentName = null)
    {
        return $this->dm->createQueryBuilder($documentName)
            ->field($this->config->getDeletedFieldName())
            ->exists(false);
    }

    /**
     * Creates a new query builder instance that will return only deleted documents
     * by adding a { deletedAt : { $exists : true } } condition.
     *
     * @param string $documentName The document class name to create the query builder for.
     * @return Doctrine\MongoDB\ODM\Query\Builder $qb
     */
    public function createDeletedQueryBuilder($documentName = null)
    {
        return $this->dm->createQueryBuilder($documentName)
            ->field($this->config->getDeletedFieldName())
            ->exists(true);
    }

    /**
     * Schedules a SoftDeleteable document for soft deletion on next flush().
     *
     * @param SoftDeleteable $document
     */
    public function delete(SoftDeleteable $document)
    {
        $this->unitOfWork->delete($document);
    }

    /**
     * Schedulds a SoftDeleteable document for soft delete restoration on next flush().
     *
     * @param SoftDeleteable $document 
     */
    public function restore(SoftDeleteable $document)
    {
        $this->unitOfWork->restore($document);
    }

    /**
     * Flushes all scheduled deletions and restorations to the database.
     */
    public function flush()
    {
        $this->unitOfWork->commit();
    }

    /**
     * Clears the UnitOfWork and erases any currently scheduled deletions or restorations.
     */
    public function clear()
    {
        $this->unitOfWork->clear();
    }
}