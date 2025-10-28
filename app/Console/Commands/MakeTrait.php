<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeTrait extends Command
{
    protected $signature = 'make:trait {name}';
    protected $description = 'Cria um Trait dentro de app/Traits';

    public function handle()
    {
        $name = $this->argument('name');
        $traitName = ucfirst($name);

        $directory = app_path('Traits');
        $filePath = "$directory/{$traitName}.php";

        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        if (File::exists($filePath)) {
            $this->error("O Trait '{$traitName}' já existe!");
            return false;
        }

        $template = <<<PHP
<?php

namespace App\Traits;

trait {$traitName}
{
    //
}

PHP;

        File::put($filePath, $template);

        $this->info("Trait {$traitName} criado com sucesso em: app/Traits/{$traitName}.php");
        return true;
    }
}
