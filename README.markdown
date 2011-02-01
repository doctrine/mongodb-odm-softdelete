# Doctrine MongoDB ODM SoftDelete Functionality

This library gives you some additional classes and API for managing the soft deleted state of Doctrine
MongoDB ODM documents. To get started you just need to configure a few objects and get a SoftDeleteManager
instance:

## Setup

    use Doctrine\ODM\MongoDB\SoftDelete\Configuration;
    use Doctrine\ODM\MongoDB\SoftDelete\UnitOfWork;
    use Doctrine\ODM\MongoDB\SoftDelete\SoftDeleteManager;
    use Doctrine\Common\EventManager;

    // $dm is a DocumentManager instance we should already have

    $config = new Configuration();
    $uow = new UnitOfWork($dm, $config);
    $evm = new EventManager();
    $sdm = new SoftDeleteManager($dm, $config, $uow, $evm);

## SoftDelete Documents

In order for your documents to work with the SoftDelete functionality they must implement the
SoftDeleteable interface:

    interface SoftDeleteable
    {
        function getDeletedAt();
        function isDeleted();
    }

An implementation might look like this:

    use Doctrine\ODM\MongoDB\SoftDelete\SoftDeleteable;

    /** @mongodb:Document */
    class User implements SoftDeleteable
    {
        // ...

        /** @mongodb:Date */
        private $deletedAt;

        public function getDeletedAt()
        {
            return $this->deletedAt;
        }
    
        public function isDeleted()
        {
            return $this->deletedAt !== null ? true : false;
        }

        // ...
    }

## Managing Soft Delete State

Once you have the $sdm you can start managing the soft delete state of your documents:

    $jwage = $dm->getRepository('User')->findOneByUsername('jwage');
    $fabpot = $dm->getRepository('User')->findOneByUsername('fabpot');
    $sdm->delete($jwage);
    $sdm->delete($fabpot);
    $sdm->flush();

The above would issue a simple query setting the deleted date:

    db.users.update({ _id : { $in : userIds }}, { $set : { deletedAt : new Date() } })

Now if we were to restore the documents:

    $sdm->restore($jwage);
    $sdm->flush();

It would unset the deletedAt date:

    db.users.update({ _id : { $in : userIds }}, { $unset : { deletedAt : true } })

## Events

We trigger some additional event lifecycle events when documents are soft deleted or restored:

* Events::preSoftDelete
* Events::postSoftDelete
* Events::preSoftDeleteRestore
* Events::postSoftDeleteRestore

Using the events is easy, just define a class like the following:

    class TestEventSubscriber implements \Doctrine\Common\EventSubscriber
    {
        public function preSoftDelete(LifecycleEventArgs $args)
        {
            $document = $args->getDocument();
            $sdm = $args->getSoftDeleteManager();
        }

        public function getSubscribedEvents()
        {
            return array(Events::preSoftDelete);
        }
    }

Now we just need to add the event subscriber to the EventManager:

    $eventSubscriber = new TestEventSubscriber();
    $evm->addEventSubscriber($eventSubscriber);

When we soft delete something the preSoftDelete() method will be invoked before any queries are sent
to the database:

    $sdm->delete($fabpot);
    $sdm->flush();

## Querying for Documents

Create a query builder that excludes deleted documents:

    $qb = $sdm->createQueryBuilder();

Create a query builder that returns only deleted documents:

    $qb = $sdm->createDeletedQueryBuilder();

If you want to modify an existing query builder to only return not deleted documents, or to only return
deleted documents, you can use the filterQueryBuilder() method:

    $qb = $dm->createQueryBuilder();
    $sdm->filterQueryBuilder(SoftDeleteManager::QUERY_NOT_DELETED, $qb);

Or you can show only deleted:

    $sdm->filterQueryBuilder(SoftDeleteManager::QUERY_DELETED, $qb);