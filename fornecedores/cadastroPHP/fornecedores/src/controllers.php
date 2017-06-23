
<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use JansenFelipe\CnpjGratis\CnpjGratis;
use JansenFelipe\NFePHPSerialize\NFePHPSerialize;
use Keboola\Csv\CsvFile;

$app->get('/', function () use ($app) {
            return $app['twig']->render('index.html.twig', array());
        })
        ->bind('homepage')
;

$app->match('/cadastro', function (Request $request) use ($app) {
            $em = new EmpresaRepository();
            $params = new CnpjGratis();
            if ($request->isMethod('POST')) {
                $receita = $params->consulta($request->request->get('cnpj'), $request->request->get('captcha'), $request->request->get('cookie'));
                $data = array('cnpj' => $request->request->get('cnpj'), 'empresa' => $receita['razao_social'], 'cidade' => $receita['cidade'], 'uf' => $receita['uf']);
                $empresa = new Empresa($data);
                $em->insertEmpresa($app, $empresa);
            }

            $empresas = $em->allEmpresas($app);
            return $app['twig']->render('cadastro/cadastro.html.twig', array('params' => $params->getParams(), 'empresas' => $empresas));
        })
        ->bind('cadastro')
;

$app->match('/upload', function (Request $request) use ($app) {
            if ($request->isMethod('POST')) {
                $files = $request->files;
                $output_dir = __DIR__ . '/../web/uploads/';
                $ret = array();
                $em = new EmpresaRepository();
                foreach ($files->all() as $file) {
                    $app['monolog']->debug(file_get_contents($file->getRealPath()));
                    $nfeProc = NFePHPSerialize::xmlToObject(file_get_contents($file->getRealPath()));
                    $empresa = $em->testCNPJ($app, $nfeProc->getNFe()->getInfNFe()->getEmit()->getCNPJ());
                    $qtMov = 0;
                    if ($empresa) {
                        #Consultando Lat e Lon
                        $enderecoNFe = $nfeProc->getNFe()->getInfNFe()->getDest()->getEnderDest();
                        $endereco = $enderecoNFe->getNro().' '.$enderecoNFe->getXLgr().', '.$enderecoNFe->getXBairro().', '.$enderecoNFe->getXMun().', '.$enderecoNFe->getUF();
                        $chave = 'AIzaSyBVCqLF3SzQy9JxXaEcCiC6sWpySevCPbs';
                        $json = file_get_contents('https://maps.googleapis.com/maps/api/geocode/json?address='.str_replace(' ', '+', $endereco).'&key='.$chave);
                        $data1 = get_object_vars(json_decode($json));
                        $data2 = get_object_vars($data1['results'][0]);
                        $data3 = get_object_vars($data2['geometry']);
                        $result_GPS = (array) $data3['location'];
                        
                        #Gerando arquivo de dados
                        $csvFile = new CsvFile($output_dir . $nfeProc->getNFe()->getInfNFe()->getId() . '.csv', ',', '');
                        $mov = array();
                        $mov['dest_nome'] = $nfeProc->getNFe()->getInfNFe()->getDest()->getXNome();
                        $mov['dest_telefone'] = $nfeProc->getNFe()->getInfNFe()->getDest()->getEnderDest()->getFone();
                        $mov['dest_Municipio'] = $nfeProc->getNFe()->getInfNFe()->getDest()->getEnderDest()->getXMun();
                        $mov['dest_UF'] = $nfeProc->getNFe()->getInfNFe()->getDest()->getEnderDest()->getUF();
                        $mov['dest_lat'] = $result_GPS['lat'];
                        $mov['dest_lng'] = $result_GPS['lng'];
                        foreach ($nfeProc->getNFe()->getInfNFe()->getDet() as $movimentacao) {
                            $mov['prod_nome'] = $movimentacao->getProd()->getXProd();
                            $mov['prod_quant'] = $movimentacao->getProd()->getQTrib();
                            $csvFile->writeRow($mov);
                            $qtMov += $movimentacao->getProd()->getQTrib();
                        }
                    }
                }
                if ($qtMov == 0) {
                    $ret[] = "Empresa não cadastrada";
                } elseif ($qtMov == 1) {
                    $ret[] = $qtMov . "  Movimentação registrada";
                } else {
                    $ret[] = $qtMov . "  Movimentaçoes registradas";
                }

                return $app->json($ret);
            }
            return $app['twig']->render('notas/upload.html.twig');
        })
        ->bind('upload')
;


$app->match('/delete/{name}', function (Request $request) use ($app) {
            $output_dir = __DIR__ . '/../web/uploads/';
            if ($request->request->get('name')) {
                $fileNameOriginal = $request->request->get('name');
                $fileName = str_replace("..", ".", $fileNameOriginal); //required. if somebody is trying parent folder files	
                $filePath = $output_dir . $fileName;
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                return $app->json("Deleted File " . $fileName);
            }
        })
        ->bind('delete')
;





$app->error(function (\Exception $e, Request $request, $code) use ($app) {
    if ($app['debug']) {
        return;
    }
    echo $e->getMessage();

    // 404.html, or 40x.html, or 4xx.html, or error.html
    $templates = array(
        'errors/' . $code . '.html.twig',
        'errors/' . substr($code, 0, 2) . 'x.html.twig',
        'errors/' . substr($code, 0, 1) . 'xx.html.twig',
        'errors/default.html.twig',
    );

    return new Response($app['twig']->resolveTemplate($templates)->render(array('code' => $code)), $code);
});
