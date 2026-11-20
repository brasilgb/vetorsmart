<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A Brazilian municipality of the IBGE registry. The primary key is the official IBGE code. Read only: the registry is
 * loaded by migration from database/data/municipios-ibge.csv.
 *
 * @property int $id
 * @property string $name
 * @property string $name_search
 * @property string $state
 */
class Municipality extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'int';

    /**
     * "Salvador do Sul, RS": the label shown to visitors.
     */
    public function label(): string
    {
        return $this->name.', '.$this->state;
    }

    /**
     * "Salvador do Sul - RS": how the municipality is written in a text field (MunicipalityDirectory::fromText reads it).
     */
    public function inputLabel(): string
    {
        return $this->name.' - '.$this->state;
    }

    /**
     * @return array{id: int, name: string, state: string, label: string, input: string}
     */
    public function toPublic(): array
    {
        return ['id' => (int) $this->id, 'name' => $this->name, 'state' => $this->state, 'label' => $this->label(), 'input' => $this->inputLabel()];
    }
}
