<?php

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductModel extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'products'; // nome da tabela
    protected $fillable = [
        'id',
        'unit_id',
        'category_id',
        'company_id',
        'product_code',
        "name",
        'description',
        'availability',
        'average_cost',
        'sale_price',
        'rental_price',
        'is_dynamic_sale_price',
        'is_dynamic_rental_price',
    ];
    protected $casts = [
        'is_dynamic_sale_price' => 'boolean',
        'is_dynamic_rental_price' => 'boolean',
    ];

    public $timestamps = false; // sua tabela não tem created_at/updated_at

     // Relacionamento com Category
    public function category()
    {
        return $this->belongsTo(ProductCategoryModel::class);
    }

    // Relacionamento com Unit
    public function unit()
    {
        return $this->belongsTo(ProductUnitsModel::class);
    }

    // ============================
    // Métodos CRUD personalizados
    // ============================
    public static function insertProduct(array $data)
    {
        return self::create($data);
    }

    // Atualizar produto por ID
    public static function updateProduct(int $id, array $data)
    {
        $product = self::find($id);
        if ($product) {
            $product->update($data);
            return $product;
        }
        return null; // produto não encontrado
    }

    // Deletar produto por ID
    public static function deleteProduct(int $id)
    {
        $product = self::find($id);
        if ($product) {
            return $product->delete();
        }
        return false; // produto não encontrado
    }

    // Selecionar todos os produtos
    public static function getAllProducts()
    {
        return self::all();
    }

    // Selecionar produto por ID
    public static function getProductById(int $id)
    {
        return self::find($id);
    }

    protected $dates = ['deleted_at'];
}
