<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="pacote.css">
    <link rel="stylesheet" href="./css/bia.css">
    <title>Pacotes Buffet</title>
</head>


<body>
<?php 
       require_once __DIR__ . DIRECTORY_SEPARATOR . 'partials' . DIRECTORY_SEPARATOR . 'header.php';
    ?>


<div class="container"> 


<h3>Pacotes:</h3>

 <div class="card-pacote">
 <h1>Pacote Comum </h1> 
<h3>O que esta incluso: </h3>

<h2>20 unidades de brigadeiro<br>
20 unidades de beijinho<br>
1 bolo tamanho médio(sabor de sua preferencia)<br>
3 doces de sua escolha</h2><br>
<p>Veja mais sobre este pacote no botão</p>
<!-- <a href="detalhes.php"><button>Ver pacote</button></a> -->
</div>

 <div class="card-pacote">
 <h1>Pacote Especial </h1> 
<h3>O que esta incluso: </h3>
<h2>
   
30 unidades de brigadeiros especiais<br>
<br>
30 unidades de macaron<br>
1 bolo tamanho grande(sabor de sua preferencia)<br>
<br>
</h2>
<button>Ver pacote</button> 
 </div>

 <div class="card-pacote">
 <h1>Pacote Premium</h1> 
<h3>O que esta incluso: </h3>
<h2>
100 unidades dos doces de sua escolha<br>
2 bolos - 1 tamanho medio e 1 grande<br>
30 </h2>
<button>Ver pacote</button> 
 </div>
 


</div>
    
</body>
</html>
