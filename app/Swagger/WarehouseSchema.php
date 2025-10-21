<?php

/**
 * @OA\Schema(
 *     schema="Warehouse",
 *     title="Warehouse",
 *     description="Warehouse resource model",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="company_id", type="integer", example=2),
 *     @OA\Property(property="address_id", type="integer", example=5),
 *     @OA\Property(property="name", type="string", example="Main Warehouse"),
 *     @OA\Property(property="note", type="string", example="Central distribution warehouse"),
 *     @OA\Property(property="status", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-10-21T15:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-10-21T15:30:00Z")
 * )
 */
