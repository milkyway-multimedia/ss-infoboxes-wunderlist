<?php namespace Milkyway\SS\InfoBoxes\Wunderlist;

/**
 * Milkyway Multimedia
 * InfoBox.php
 *
 * @package milkyway-multimedia/ss-infoboxes-wunderlist
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */

use Milkyway\SS\InfoBoxes\Wunderlist\Contracts\Provider;
use Milkyway\SS\Config;
use Exception;

class InfoBox_Wunderlist implements \InfoBox {
    protected $provider;
    protected $listId;
    protected $task;

    public function __construct(Provider $provider, $listId = '') {
        $this->provider = $provider;
        $this->listId = $listId;
    }

    public function show() {
        return $this->task();
    }

    public function message() {
        return $this->task();
    }

    public function severity() {
        return 2;
    }

    public function link() {
        return false;
    }

    protected function task() {
        if($this->task === null) {
            $listId = $this->getListId();
            $tasks = array_filter($this->provider->get('me/tasks'), function($task) use($listId) {
                return $listId ? isset($task['list_id']) && $task['list_id'] == $listId ? true : false : isset($task['title']) && $task['title'] ? true : false;
            });
            $this->task = count($tasks) ? array_shift($tasks) : '';
            $this->task = $this->task['title'];
        }

        return $this->task;
    }

    protected function getListId() {
        if($this->listId)
            return $this->listId;

        $listId = Config::get('infoboxes_wunderlist.list_id');
        $listTitle = Config::get('infoboxes_wunderlist.list');

        if(!$listId && $listTitle && file_exists($this->listLocation())) {
            $list = file_get_contents($this->listLocation());
            $listId = $list['id'];
        }

        if(!$listId && $listTitle) {
            $lists = array_filter($this->provider->get('me/lists'), function($list) use($listTitle) {
                return isset($list['title']) && $list['title'] == $listTitle && isset($list['id']);
            });

            if(!count($lists))
                throw new Exception(sprintf('No list could be found with the title: %s', $listTitle));

            $list = array_shift($lists);

            file_put_contents($this->listLocation(), $list);
            $listId = $list['id'];
        }

        $this->listId = $listId;

        return $listId;
    }

    private function listLocation() {
        return TEMP_FOLDER . DIRECTORY_SEPARATOR . '.' . str_replace('\\', '_', get_class($this)) . '_list';
    }
} 