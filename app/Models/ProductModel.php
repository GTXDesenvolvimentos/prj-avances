<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductModel extends Model
{
    use HasFactory;

    protected $table = 'products'; // nome da tabela
    protected $fillable = [
        'product_code',
        'description',
        'category_id',
        'supplier_id',
        'unit_id',
        'cost_price',
        'sale_price',
    ];

    public $timestamps = false; // sua tabela não tem created_at/updated_at

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
}
