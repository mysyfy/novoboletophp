<?php
namespace NovoBoletoPHP;

use \NovoBoletoPHP\FormatterHelper;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;

class BoletoFactory {
    private $twig;
    public $layouts;
    public $imageUrl;
    public $config;

    const BANCO_DO_BRASIL = 1;
    const SANTANDER = 33;
    const ITAU = 341;

    public function __construct(array $config = array())
    {
        $this->config = $config;

        $basePath = __DIR__ . '/../..';
        $templatePath = $basePath . '/templates';
        $this->imageUrl = array_key_exists('imageUrl', $config) ?
                          $config['imageUrl'] :
                          '/images';
        $cachePath = array_key_exists('cachePath', $config) ?
                     $config['cachePath'] :
                     $basePath . '/cache';

        $loader     = new FilesystemLoader($templatePath);

        $this->twig = new Environment($loader, array(
            'cache' => $cachePath,
        ));

        $this->layouts = array(
            self::BANCO_DO_BRASIL   => '\NovoBoletoPHP\BancoDoBrasil\Boleto',
            self::SANTANDER         => '\NovoBoletoPHP\Santander\Boleto',
            self::ITAU              => '\NovoBoletoPHP\Itau\Boleto'
        );

        $this->configure();
    }

    public function configure()
    {

        $this->twig->addFilter(new TwigFilter('imageUrl', array($this, 'makeImageUrl'), array(
            'is_safe' => array('html')
        )));
        $this->twig->addFilter(new TwigFilter('barcode', array($this, 'barcode'), array(
            'is_safe' => array('html')
        )));
    }

    public function makeBoleto($layout, $data)
    {
        $clazz = $this->layouts[$layout];
        $boleto = new $clazz($this->twig, $data);
        return $boleto;
    }

    public function makeBoletoAsHTML($banco, $data)
    {
        return $this->makeBoleto($banco, $data)->asHTML();
    }

    public function makeBoletoAsPDF($banco, $data)
    {
        $html = $this->makeBoletoAsHTML($banco, $data);
        $tmpfname1 = tempnam(sys_get_temp_dir(), 'wkhtml').'.html';
        $tmpfname2 = tempnam(sys_get_temp_dir(), 'wkhtml').'.pdf';
        file_put_contents($tmpfname1, $html);
        $output = array();
        $return = null;
        exec("xvfb-run -a -s '-screen 0 640x480x16' wkhtmltopdf --page-size A5 --margin-left 10mm --margin-right 10mm --zoom 2 {$tmpfname1} {$tmpfname2} 2>&1", $output, $return);
        if($return != 0)
            throw new \Exception(implode("<br />", $output));
        if(!file_exists($tmpfname2))
            throw new \Exception("Arquivo não gerado em $tmpfname2");
        return file_get_contents($tmpfname2);
    }

    public function makeImageUrl($imageName)
    {
        if (strstr($imageName, '://') || strpos('/', $imageName) === 0) {
            return $imageName;
        } else {
            return $this->imageUrl . '/' . $imageName;    
        }
        
    }

    public function barcode($valor)
    {
        ob_start();

        $fino = 1 ;
        $largo = 3 ;
        $altura = 50 ;

        $barcodes[0] = "00110" ;
        $barcodes[1] = "10001" ;
        $barcodes[2] = "01001" ;
        $barcodes[3] = "11000" ;
        $barcodes[4] = "00101" ;
        $barcodes[5] = "10100" ;
        $barcodes[6] = "01100" ;
        $barcodes[7] = "00011" ;
        $barcodes[8] = "10010" ;
        $barcodes[9] = "01010" ;

        for ($f1=9; $f1>=0; $f1--) {
            for ($f2=9; $f2>=0; $f2--) {
                $f = ($f1 * 10) + $f2 ;
                $texto = "" ;

                for ($i=1; $i<6; $i++) {
                    $texto .=  substr($barcodes[$f1],($i-1),1) . substr($barcodes[$f2], ($i-1), 1);
                }

                $barcodes[$f] = $texto;
            }
        }

        ?>
        <img src=<?php echo $this->makeImageUrl('p.png'); ?> width=<?php echo $fino?> height=<?php echo $altura?> border=0><img 
        src=<?php echo $this->makeImageUrl('b.png'); ?> width=<?php echo $fino?> height=<?php echo $altura?> border=0><img 
        src=<?php echo $this->makeImageUrl('p.png'); ?> width=<?php echo $fino?> height=<?php echo $altura?> border=0><img 
        src=<?php echo $this->makeImageUrl('b.png'); ?> width=<?php echo $fino?> height=<?php echo $altura?> border=0><img 
        <?php
        $texto = $valor;

        if ((strlen($texto) % 2) <> 0) {
            $texto = "0" . $texto;
        }

        // Draw dos dados
        while (strlen($texto) > 0) {
          $i = round(FormatterHelper::esquerda($texto,2));
          $texto = FormatterHelper::direita($texto,strlen($texto)-2);
          $f = $barcodes[$i];
          for($i=1;$i<11;$i+=2){
            if (substr($f,($i-1),1) == "0") {
              $f1 = $fino ;
            }else{
              $f1 = $largo ;
            }
        ?>
            src=<?php echo $this->makeImageUrl('p.png'); ?> width=<?php echo $f1?> height=<?php echo $altura?> border=0><img 
        <?php
            if (substr($f,$i,1) == "0") {
              $f2 = $fino ;
            }else{
              $f2 = $largo ;
            }
        ?>
            src=<?php echo $this->makeImageUrl('b.png'); ?> width=<?php echo $f2?> height=<?php echo $altura?> border=0><img 
        <?php
          }
        }

    // Draw guarda final
    ?>
    src=<?php echo $this->makeImageUrl('p.png'); ?> width=<?php echo $largo?> height=<?php echo $altura?> border=0><img 
    src=<?php echo $this->makeImageUrl('b.png'); ?> width=<?php echo $fino?> height=<?php echo $altura?> border=0><img 
    src=<?php echo $this->makeImageUrl('p.png'); ?> width=<?php echo 1?> height=<?php echo $altura?> border=0> 
      <?php
      
        $result = ob_get_contents();
        ob_end_clean();
        return $result;
    }
}
