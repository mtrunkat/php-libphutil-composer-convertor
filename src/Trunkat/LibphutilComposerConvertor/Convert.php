<?php

namespace Trunkat\LibphutilComposerConvertor;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class Convert extends Command
{
    const NEW_NAMESPACE = 'Facebook\Libphutil';
    const NEW_NAMESPACE_ESCAPED = '\\Facebook\\Libphutil\\';

    /** All the possible prefixes of classes and functions */
    private $prefixes = array(
        'class'    => array('(', '{', '[', ' ', ';', '.', '!', "'", '"'),
        'function' => array('(', '{', '[', ' ', ';', '.', '!', "'", '"'),
    );
    /** All the possible suffixes of classes and functions */
    private $suffixes = array(
        'class'    => array(' ', "\n", ';', '(', ')', '}', ']', ',', '::', "'", '"'),
        'function' => array('(', ' (', "'", '"'),
    );

    /**
     * Command configuration..
     */
    protected function configure()
    {
        $this
            ->setName('convert')
            ->setDescription('Make libphutil composer compatible.')
            ->addArgument(
                'source',
                InputArgument::REQUIRED,
                'Source directory containing libphutil library.'
            )
            ->addArgument(
                'target',
                InputArgument::REQUIRED,
                'Target directory for final composer version of libphutil library.'
            )
        ;
    }

    /**
     * Command execution.
     * 
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $replace = $this->getReplacementForPhpInternals();
        $source  = $input->getArgument('source') . '/src';
        $target  = $input->getArgument('target') . '/src';

        $fs = new Filesystem();
        $fn = new Finder();

        // Clean old and recreate target directory.
        $fs->remove($target);
        $target .= '/' . str_replace('\\', '/', self::NEW_NAMESPACE);
        $fs->mkdir($target);
        $output->writeln('Directory cleaned.');

        // Explore files and copy them to new location.
        $classFiles    = array();
        $functionFiles = array();
        $count         = 0;
        $functionLoc   = '';
        foreach ($fn->files()->exclude('__tests__')->name('*.php')->in($source) as $file) {
            $pathName  = $file->getRelativePathname();
            $fileName  = $file->getFileName();
            $baseName  = basename($fileName, '.php');

            $contents  = $file->getContents();
            $classes   = $this->getPhpClasses($contents);
            $functions = $this->getPhpFunctions($contents);

            if ($classes) {
                $targetPath = "$target/$fileName";

                $classFiles[] = $targetPath;

                $fs->copy("$source/$pathName", "$target/$fileName");

                foreach ($classes as $class) {
                    foreach ($this->suffixes['class'] as $suffix) {
                        foreach ($this->prefixes['class'] as $prefix) {                         
                            $replace["$prefix$class$suffix"]  = "$prefix" . self::NEW_NAMESPACE_ESCAPED . "$class$suffix";
                        }

                        $replace["class "     . self::NEW_NAMESPACE_ESCAPED . "$class$suffix"] = "class $class$suffix";
                        $replace["interface " . self::NEW_NAMESPACE_ESCAPED . "$class$suffix"] = "interface $class$suffix";
                        $replace["trait "     . self::NEW_NAMESPACE_ESCAPED . "$class$suffix"] = "interface $class$suffix";
                    }
                }

                $count ++;
            } elseif ($functions) {
                $targetPath = "$target/Functions/$fileName";
                $functionFiles[] = $targetPath;
                $fs->copy("$source/$pathName", $targetPath);

                foreach ($functions as $function) {
                    foreach ($this->suffixes['function'] as $suffix) {
                        foreach ($this->prefixes['function'] as $prefix) {                          
                            $replace["$prefix$function$suffix"]  = "$prefix" . self::NEW_NAMESPACE_ESCAPED . "Functions\\$baseName::$function$suffix";
                        }

                        $replace["function " . self::NEW_NAMESPACE_ESCAPED . "Functions\\$baseName::$function$suffix"]  = "static function $function$suffix";
                    }

                    $functionLoc .= "$function() ....................... $prefix" . self::NEW_NAMESPACE_ESCAPED . "Functions\\$baseName::$function$suffix\n";
                }

                $count ++;
            } else {
                $output->writeln("Skipping $pathName");
            }
        }     

        $output->writeln("$count files copied to new location.");
        $output->writeln("Starting replacement of " . count($replace) . " strings.");

        $this->processFunctionFiles($functionFiles, $replace);
        $this->processClassFiles($classFiles, $replace);

        file_put_contents("$target/../../function_mapping.txt", $functionLoc);

        $output->writeln('Done.');
    }

    /**
     * Returnes array containing names of all the php classes, interfaces and traits.
     * 
     * @param array 
     */
    private function getReplacementForPhpInternals()
    {
        $replace = array();

        $classLikes = array_merge(
            get_declared_classes(), 
            get_declared_traits(), 
            get_declared_interfaces()
        );

        foreach ($this->suffixes['class'] as $suffix) {
            foreach ($this->prefixes['class'] as $prefix) { 
                foreach ($classLikes as $class) {
                    $replace["$prefix$class$suffix"]  = "$prefix\\$class$suffix";
                }
            }
        }

        return $replace;
    }

    /**
     * Performes all needed changes in files containing functions.
     * 
     * @param array $functionFiles
     * @param array $replace 
     */
    private function processFunctionFiles($functionFiles, $replace) 
    {
        foreach ($functionFiles as $file) {
            $contents = file_get_contents($file);
            $contents = $this->replace($contents, $replace);
            $contents = $this->indent($contents);
            $contents = str_replace('<?php', "<?php\n\nnamespace " . self::NEW_NAMESPACE . "\Functions;\n\nclass " . basename($file, '.php') . " {", $contents);
            $contents .= "\n}\n";

            file_put_contents($file, $contents);
        }       
    }

    /**
     * Performes all needed changes in files containing classes.
     * 
     * @param array $classFiles
     * @param array $replace 
     */
    private function processClassFiles($classFiles, $replace) 
    {
        foreach ($classFiles as $file) {
            $contents = file_get_contents($file);
            $contents = $this->replace($contents, $replace);
            $contents = str_replace('<?php', "<?php\n\nnamespace " . self::NEW_NAMESPACE . ";", $contents);

            file_put_contents($file, $contents);
        }
    }

    /**
     * Replaces keys by values of $replacements array in $contents string.
     * 
     * @param string $contents 
     * @param array  $replacements
     * 
     * @return string 
     */
    private function replace($contents, $replacements) 
    {
        foreach ($replacements as $search => $replace) {
            $contents = str_replace($search, $replace, $contents);
        }

        return $contents;
    }

    /**
     * Shifts given php code by two spaces right.
     * 
     * @param string $contents
     * 
     * @return string
     */
    private function indent($contents) 
    {
        $contents = explode("\n", $contents);

        foreach ($contents as $id => $row) {
            // Skip first line.
            if ($id) {
                $contents[$id] = "  $row";
            }
        }

        return implode("\n", $contents);
    }

    /**
     * Returnes names of classes contained in given php code.
     * 
     * http://stackoverflow.com/a/6847175
     * http://stackoverflow.com/users/819557/hajamie
     * 
     * @param string $phpCode
     * 
     * @return array
     */
    function getPhpClasses($phpCode)
    {
        $classes = array();
        $tokens  = token_get_all($phpCode);
        $count   = count($tokens);
        $allowed = array(T_CLASS, T_INTERFACE, T_TRAIT);

        for ($i = 2; $i < $count; $i++) {
            if (in_array($tokens[$i - 2][0], $allowed) && $tokens[$i - 1][0] == T_WHITESPACE && $tokens[$i][0] == T_STRING) {
                $classes[] = $tokens[$i][1];
                break;
            }
        }

        return $classes;
    }

    /**
     * Returnes names of functions contained in given php code.
     * 
     * http://stackoverflow.com/questions/2197851/function-list-of-php-file
     * http://stackoverflow.com/users/1714329/sirwilliam
     * 
     * @param string $phpCode
     * 
     * @return array
     */
    private function getPhpFunctions($phpCode)
    {
        $arr = token_get_all($phpCode);
        $functions = array();

        foreach($arr as $key => $value){
            //filter all user declared functions
            if($value[0] == 334){
                //Take a look for debug sake
                //echo $value[0] .' -|- '. $value[1] . ' -|- ' . $value[2] . '<br>';
                //store third value to get relation
                $chekid = $value[2];
            }

             //now list functions user declared (note: The last chek is to ensure we only get the first peace of information about  ...
            // ... the function witch is the function name, else we also list other function header information like preset values)
            if($value[2] == $chekid && $value[0] == 307 && $value[2] != $old){
                //echo $value[0] .' -|- '. $value[1] . ' -|- ' . $value[2] . '<br>';
                $functions[] = $value[1];
                $old = $chekid; 
            }
        }

        return $functions;
    }
}