<?php

namespace digitalpulsebe\craftmultitranslator\records;

use craft\db\ActiveRecord;
use digitalpulsebe\craftmultitranslator\MultiTranslator;

/**
 * @property int $id
 * @property string $name
 * @property string $deeplId
 * @property string $sourceLanguage
 * @property string $targetLanguage
 * @property array $data
 */
class Glossary extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return '{{%multitranslator_deepl_glossaries}}';
    }

    /**
     * map saved json array to array usable in UI
     * @return array
     */
    public function getRows()
    {
        if (is_string($this->getAttribute('data'))) {
            $data = json_decode($this->getAttribute('data'), true);
        } else {
            $data = $this->getAttribute('data');
        }

        if (is_array($data)) {
            $rows = [];

            foreach ($data as $key => $value) {
                $rows[] = ['source' => $key, 'target' => $value];
            }

            return $rows;
        } else {
            return [];
        }
    }

    /**
     * map array of frontend table to Database and API format
     * @param array $rows
     * @return array
     */
    public function setRows(array $rows)
    {
        $data = [];
        foreach ($rows as $row) {
            $data[$row['source']] = $row['target'];
        }

        $this->setAttribute('data', $data);

        return $data;
    }

    public static function createOrUpdate(array $data)
    {
        $isExisting = true;

        if (!empty($data['id'])){
            $item = self::findOne(['id'=>$data['id']]);
        }

        if (empty($item)) {
            $isExisting = false;
            $item = new self();
        }
        $item->name = $data['name'];
        $item->sourceLanguage = $data['sourceLanguage'];
        $item->targetLanguage = $data['targetLanguage'];

        if(!$item->validate()) {
            return $item;
        }

        if (is_array($data['rows']) && count($data['rows']) > 0) {
            $data['rows'] = array_filter(
                $data['rows'],
                function ($row) {
                    return !(empty(trim($row['source'])) || empty(trim($row['target'])));
                }
            );

            $rows = $item->setRows($data['rows']);
        } else {
            $item->addError('rows', 'Content empty');
            return $item;
        }

        if ($isExisting) {
            // there is no update method, so delete existing first
            $item->deleteApiRecord();
        }

        // create (again) in Deepl API
        $deeplGlossary = MultiTranslator::getInstance()->deepl->createGlossary($item->name, $item->sourceLanguage, $item->targetLanguage, $rows);

        // save returned id
        $item->deeplId = $deeplGlossary->glossaryId;

        $item->save();

        return $item;
    }

    public function delete(): false|int
    {
        $this->deleteApiRecord();
        return parent::delete();
    }

    protected function deleteApiRecord(): void
    {
        if (!empty($this->deeplId)) {
            try {
                MultiTranslator::getInstance()->deepl->deleteGlossary($this->deeplId);
            } catch (\Throwable $e) {
                // might fail, but we still want to continue
                MultiTranslator::error([
                    'message' => 'Error when deleting existing Deepl Glossary',
                    'glossaryId' => $this->deeplId,
                ]);
            }

            $this->deeplId = null;
        }
    }

    public function rules()
    {
        return [
            [['name', 'sourceLanguage', 'targetLanguage'], 'required'],
            [['name', 'sourceLanguage', 'targetLanguage'], 'trim'],
            ['sourceLanguage', 'compare', 'compareAttribute' => 'targetLanguage', 'operator' => '!='],
            [['sourceLanguage', 'targetLanguage'], 'unique', 'targetAttribute' => ['sourceLanguage', 'targetLanguage']],
        ];
    }
}
