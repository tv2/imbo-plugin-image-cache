<?php
namespace Tv2\Imbo\Plugins;

use Imbo\EventListener\ListenerInterface,
    Imbo\EventManager\EventInterface,
    Imbo\Storage\StorageInterface,
    Imbo\Exception\StorageException;

/**
 * A event-listener for caching original images, in cache the original images
 * are stored in a locatino with long retrieval times
 *
 * @author Morten Fangel <fangel@sevengoslings.net>
 * @package Event\Listeners
 */
class ImageCache implements ListenerInterface {
  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      'storage.image.load' => ['retrieveFromCache' => 100],
      'image.loaded' => ['storeInCache' => -100],
      'storage.image.delete' => 'deleteCache',
    ];
  }

  /**
   * A instance of the storage-interface to use as a cache
   *
   * @var Imbo\Storage\StorageInterface
   */
  private $storage;

  /**
   * Class constructor
   *
   * @param array $params Parameters for the driver
   */
  public function __construct(StorageInterface $storage) {
    $this->storage = $storage;
  }

  /**
   * Tries to retrieve a cache of the original image from the image-cache
   *
   * @param EventInterface $event An event instance
   */
  public function retrieveFromCache(EventInterface $event) {
    $request = $event->getRequest();
    $response = $event->getResponse();

    $user = $request->getUser();
    $imageIdentifier = $request->getImageIdentifier();

    // Attempt to load the image-cache from the cache
    try {
      $file = $this->storage->getImage($user, $imageIdentifier);
      $cacheData = @unserialize($file);

      // Check the stored data to see if it is what we expect
      if (
        $cacheData &&
        isset($cacheData['lastModified']) &&
        isset($cacheData['image']) && 
        $cacheData['lastModified'] instanceof \DateTime
      ) {
        // It was, so we can use the image from here, and stop this load-event.
        $response->setLastModified($cacheData['lastModified'])
                 ->getModel()->setBlob($cacheData['image']);
        $response->headers->set('X-Tv2-Imbo-ImageCache', 'Hit');

        // Stop the load-event, to prevent the normal storage-handler from doing
        // anything
        $event->stopPropagation();

        // But then we need to manually call the image.loaded event that is
        // normally triggered upon success by the normal storage handler.
        $event->getManager()->trigger('image.loaded');
      } else {
        // The data stored in the cache didn't make sense - so delete the cache
        $this->storage->delete($user, $imageIdentifier);
        $response->headers->set('X-Tv2-Imbo-ImageCache', 'Miss');
      }
    } catch (StorageException $e) {
      // There was an error while trying to retrieve the cache, so we simply
      // mark it as a cache-miss.
      $response->headers->set('X-Tv2-Imbo-ImageCache', 'Miss');
    }
  }

  /**
   * Delete the cache when we delete the source image
   *
   * @param EventInterface $event An event instance
   */
  public function deleteCache(EventInterface $event) {
    $request = $event->getRequest();

    $user = $request->getUser();
    $imageIdentifier = $request->getImageIdentifier();

    try {
      $this->storage->delete($user, $imageIdentifier);
    } catch (StorageException $e) {
      // Silently swollow errors...
    }
  }

  /**
   * Store the image in the cache once a original image have been loaded
   *
   * @param EventInterface $event An event instance
   */
  public function storeInCache(EventInterface $event) {
    $request = $event->getRequest();
    $response = $event->getResponse();
    $model = $response->getModel();

    $user = $request->getUser();
    $imageIdentifier = $request->getImageIdentifier();
    $file = $model->getBlob();

    // We need to store both the last modified as well as the image, so we
    // store a serialized array containing the two pieces of infomation.
    $cacheData = [
      'lastModified' => $response->getLastModified(),
      'image' => $file
    ];

    try {
      $this->storage->store($user, $imageIdentifier, serialize($cacheData));
    } catch (StorageException $e) {
      // Silently swollow errors...
    }
  }
}