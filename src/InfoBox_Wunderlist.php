<?php namespace Milkyway\SS\InfoBoxes\Wunderlist;

/**
 * Milkyway Multimedia
 * InfoBox.php
 *
 * @package milkyway-multimedia/ss-infoboxes-wunderlist
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use Milkyway\SS\InfoBoxes\Wunderlist\Contracts\Provider as ProviderContract;
use Exception;

class InfoBox_Wunderlist implements \InfoBox
{
    protected $provider;
    protected $listId;
    protected $task;

    public function __construct(ProviderContract $provider, $listId = '')
    {
        $this->provider = $provider;
        $this->listId = $listId;
    }

    public function show()
    {
        return $this->task() && !empty($this->task());
    }

    public function message()
    {
        return $this->task()['title'];
    }

    public function severity()
    {
        return isset($this->task()['starred']) && $this->task()['starred'] ? 1 : 2;
    }

    public function link()
    {
        return false;
    }

    protected function task()
    {
        if ($this->task === null) {
            try {
                $listId = $this->getListId();
                $tasks = array_filter($this->provider->get('tasks', [
                    'list_id' => $listId,
                ]), function ($task) use ($listId) {
                    return $this->isTaskValid($task, $listId);
                });

                $this->task = !empty($tasks) ? array_shift($tasks) : [];
            } catch (Exception $e) {
                $this->task = [];
            }
        }

        return $this->task;
    }

    protected function getListId()
    {
        if ($this->listId) {
            return $this->listId;
        }

        $listId = singleton('env')->get('infoboxes.wunderlist.list_id');
        $listTitle = singleton('env')->get('infoboxes.wunderlist.list');

        if (!$listId && $listTitle && file_exists($this->listLocation())) {
            $list = json_decode(file_get_contents($this->listLocation()), true);
            $listId = $list['id'];
        }

        if (!$listId && $listTitle) {
            $lists = array_filter($this->provider->get('lists'), function ($list) use ($listTitle) {
                return isset($list['title']) && strtolower($list['title']) == strtolower($listTitle) && isset($list['id']);
            });

            if (empty($lists)) {
                throw new Exception(sprintf('No list could be found with the title: %s', $listTitle));
            }

            $list = array_shift($lists);

            file_put_contents($this->listLocation(), json_encode($list));
            $listId = $list['id'];
        }

        $this->listId = $listId;

        return $listId;
    }

    protected function isTaskValid(array $task, $listId = '')
    {
        if ($listId && isset($task['list_id']) && $task['list_id'] != $listId) {
            return false;
        }

        if (!isset($task['title']) || !$task['title']) {
            return false;
        }

        foreach (['completed_at', 'deleted_at'] as $doneDate) {
            if (isset($task[$doneDate]) && $task[$doneDate]) {
                return false;
            }
        }

        return true;
    }

    private function listLocation()
    {
        return TEMP_FOLDER . DIRECTORY_SEPARATOR . '.' . str_replace('\\', '_', get_class($this)) . '_list';
    }
} 