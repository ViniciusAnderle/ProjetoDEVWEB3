<?php
session_start(); // Inicie a sessão no início do arquivo

include '../../conexao.php'; // Inclua o arquivo de conexão com o banco de dados

// Verifique se o usuário está logado
if (!isset($_SESSION['login'])) {
    echo '<script>alert("Faça login para acessar o carrinho");</script>';
    echo '<script>setTimeout(function(){ window.location.href = "../../login.php"; });</script>';
    exit();
}

$tipo_usuario = isset($_SESSION['tipo_usuario']) ? $_SESSION['tipo_usuario'] : null;

if ($tipo_usuario !== null && $tipo_usuario != 2) {
    echo '<script>alert("Você não tem permissão para acessar esta página.");</script>';
    echo '<script>setTimeout(function(){ window.location.href = "../../index.php"; });</script>';
    exit();
}

// Verifique se o ID do produto foi enviado por POST
if (isset($_POST['id_produto']) && isset($_POST['action'])) {
    $id_produto = intval($_POST['id_produto']);
    $id_usuario = intval($_SESSION['id']); // Obter o ID do usuário da sessão

    if ($_POST['action'] === 'remove') {
        // Remove um item do carrinho
        $stmt = $conn->prepare("DELETE FROM carrinho WHERE id_usuario = ? AND id_produto = ? LIMIT 1");
        $stmt->bind_param("ii", $id_usuario, $id_produto);
        $stmt->execute();

        // Redireciona o usuário para a mesma página usando GET
        header("Location: $_SERVER[PHP_SELF]");
        exit();
    } elseif ($_POST['action'] === 'add') {
        // Adiciona um item ao carrinho
        $stmt = $conn->prepare("INSERT INTO carrinho (id_usuario, id_produto) VALUES (?, ?)");
        $stmt->bind_param("ii", $id_usuario, $id_produto);
        $stmt->execute();

        // Definir uma mensagem de sucesso para exibir
        $mensagem = "Produto adicionado ao carrinho.";
        // Exibir a mensagem de produto adicionado ao carrinho por 3 segundos
        echo "<script>setTimeout(function() { document.getElementById('mensagem').style.display = 'none'; }, 3000);</script>";

        // Redireciona o usuário para a mesma página usando GET
        header("Location: $_SERVER[PHP_SELF]");
        exit();
    }
}

// Atualizar a quantidade de itens no carrinho na sessão
$stmt_quantidade = $conn->prepare("SELECT COUNT(id_produto) as quantidade FROM carrinho WHERE id_usuario = ?");
$stmt_quantidade->bind_param("i", $id_usuario);
$stmt_quantidade->execute();
$result_quantidade = $stmt_quantidade->get_result();
$row_quantidade = $result_quantidade->fetch_assoc();
$_SESSION['quantidade_carrinho'] = $row_quantidade['quantidade'];
$stmt_quantidade->close();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carrinho</title>
    <link rel="stylesheet" type="text/css" href="assets/css/stylecarrinho.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        function alterarQuantidade(id_produto, action) {
            $.ajax({
                type: "POST",
                url: "alterar_quantidade.php",
                data: {
                    id_produto: id_produto,
                    action: action
                },
                success: function(response) {
                    location.reload(); // Recarrega a página após a atualização
                }
            });
        }
    </script>
</head>

<body>
    <h1>Carrinho</h1>

    <?php
    // Consulta SQL para obter os produtos no carrinho do usuário logado
    $id_usuario = intval($_SESSION['id']);
    $stmt = $conn->prepare("SELECT c.id_produto, COUNT(c.id_produto) as quantidade, p.nome, p.descricao, p.preco, p.imagem FROM carrinho c INNER JOIN produto p ON c.id_produto = p.id WHERE c.id_usuario = ? GROUP BY c.id_produto");
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $total = 0; // Variável para armazenar o total dos produtos no carrinho

        while ($row = $result->fetch_assoc()) {
            $total += $row['preco'] *
                $row['quantidade']; // Adiciona o preço do produto ao total
            $descricao = explode(' ', $row["descricao"]);
            $descricao = array_slice($descricao, 0, 20);
            $descricao = implode(' ', $descricao);

            echo "<form method='post' action='../../src/view/produto.php'>";
            echo "<input type='hidden' name='id' value='" . $row["id_produto"] . "'>";
            echo "<button type='submit' style='background: none; border: none; padding: 0; margin: 0;' class='product-image-button'>";
            echo "<img src='data:image/jpeg;base64," . base64_encode($row['imagem']) . "' alt='" . $row['nome'] . "' class='product-image'>";
            echo "</button>";
            echo "</form>";

            echo "<p>{$descricao} - R$ {$row['preco']} - Quantidade: {$row['quantidade']}</p>";

            echo "<button type='button' onclick='alterarQuantidade({$row['id_produto']}, \"remove\")' class='button'>-</button>";

            echo "<button type='button' onclick='alterarQuantidade({$row['id_produto']}, \"add\")' class='button'>+</button>";
        }

        // Exibe o valor total
        echo "<p><strong>Total: R$ $total</strong></p>";

        // Botão Comprar
        echo "<form method='post'>";
        echo "<input type='submit' name='comprar' value='Comprar' class='button'>";
        echo "</form>";

        if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['comprar'])) {
            // Limpa todos os itens do carrinho no banco de dados
            $stmt_delete = $conn->prepare("DELETE FROM carrinho WHERE id_usuario = ?");
            $stmt_delete->bind_param("i", $id_usuario);
            if ($stmt_delete->execute()) {
                // Recarrega a página após 2 segundos
                echo "<script>
                setTimeout(function() {
                    window.location.href = '../../index.php';
                }, 500);
            </script>";
                echo "<div id='compra-realizada' style='background-color: #dff0d8; color: #
                3c763d; padding: 10px; margin-top: 10px;'>Compra realizada!</div>";
            } else {
                echo "Erro ao realizar a compra: " . $conn->error;
            }
        }
    } else {
        echo "<p>Carrinho vazio</p>";
    }
    ?>


    <a href="../../index.php">Voltar para a página inicial</a>



</body>

</html>


<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Se o formulário foi submetido, processa o salvamento do endereço



    // Verifica se todos os campos estão preenchidos
    if (empty($cep) || empty($cidade) || empty($estado) || empty($rua) || empty($bairro) || empty($numero) || empty($usuario_id)) {
        exit;
    }
    // Insere os dados do endereço no banco de dados
    $stmt = $conn->prepare("INSERT INTO enderecos (cep, cidade, estado, rua, bairro, numero, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssi", $cep, $cidade, $estado, $rua, $bairro, $numero, $usuario_id);
    if ($stmt->execute()) {
        echo "Endereço salvo com sucesso.";
    } else {
        echo "Erro ao salvar endereço.";
    }
}
// Consulta os endereços do usuário logado
$usuario_id = $_SESSION['id'];
$stmt = $conn->prepare("SELECT * FROM enderecos WHERE usuario_id = ?");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html>

<head>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>

<body>
    <button id="cadastrarNovoEndereco">Cadastrar Novo Endereço</button>
    <button id="usarEnderecoExistente">Usar Endereço Existente</button>
    <form id="enderecoForm" method="post" style="display: none;">
        <h2>Endereço</h2>
        <label for="cep">CEP:</label>
        <input type="text" id="cep" name="cep" maxlength="9" required><br>
        <label for="cidade">Cidade:</label>
        <input type="text" id="cidade" name="cidade" required><br>
        <label for="estado">Estado:</label>
        <input type="text" id="estado" name="estado" required><br>
        <label for="rua">Rua:</label>
        <input type="text" id="rua" name="rua" required><br>
        <label for="bairro">Bairro:</label>
        <input type="text" id="bairro" name="bairro" required><br>
        <label for="numero">Número:</label>
        <input type="number" text-decoration="none" id="numero" name="numero" required><br>
        <input type="submit" value="Salvar Endereço" class="button">
        <div id="mensagem"></div> <!-- Div para exibir mensagem de sucesso ou erro -->
    </form>
    <div id="enderecoSelecionado"></div>

    <table id="listaEnderecos" border="1" style="display: none;">
        <tr>
            <th>CEP</th>
            <th>Cidade</th>
            <th>Estado</th>
            <th>Rua</th>
            <th>Bairro</th>
            <th>Número</th>
            <th>Selecionar</th>
        </tr>
    </table>
    <script>
        // Função para atualizar a lista de endereços
        function atualizarListaEnderecos() {
            $.ajax({
                url: 'listar_enderecos.php', // Arquivo PHP para listar os endereços
                success: function(data) {
                    $('#listaEnderecos').html(data);
                    $('.selecionarEndereco').show();
                }
            });
        }
        $(document).ready(function() {
            // Ao clicar no botão "Cadastrar Novo Endereço"
            $('#cadastrarNovoEndereco').click(function() {
                $('#enderecoForm').show();
                $('#listaEnderecos, #enderecoSelecionado').hide();
                $('#cep, #cidade, #estado, #rua, #bairro, #numero').val('');
                $('#mensagem').text('');
            });
            // Ao clicar no botão "Usar Endereço Existente"
            $('#usarEnderecoExistente').click(function() {
                $('#enderecoForm').hide();
                $('#listaEnderecos').show();
                $('#enderecoSelecionado').text('');
                $('#mensagem').text('');
                atualizarListaEnderecos();
            });
            // Ao clicar em um botão "Selecionar" da tabela de endereços
            $(document).on('click', '.selecionarEndereco', function() {
                var cep = $(this).data('cep');
                var cidade = $(this).data('cidade');
                var estado = $(this).data('estado');
                var rua = $(this).data('rua');
                var bairro = $(this).data('bairro');
                var numero = $(this).data('numero');
                // Preenche os campos do formulário com os dados do endereço selecionado
                $('#cep').val(cep);
                $('#cidade').val(cidade);
                $('#estado').val(estado);
                $('#rua').val(rua);
                $('#bairro').val(bairro);
                $('#numero').val(numero);
                // Exibe uma mensagem indicando o endereço selecionado
                var enderecoSelecionado = "Endereço selecionado: " + rua + ", " + numero + " - " + bairro + " - " + cidade + "/" + estado + " - CEP: " + cep;
                $('#enderecoSelecionado').text(enderecoSelecionado);
            });
            // Ao enviar o formulário de cadastro de endereço
            $('#enderecoForm').submit(function(e) {
                e.preventDefault(); // Evita que o formulário seja enviado normalmente
                var formData = new FormData(this); // Cria um objeto FormData com os dados do formulário
                $.ajax({
                    type: 'POST',
                    url: 'cep.php', // Seu arquivo PHP para salvar o endereço
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(response) {
                        // Obtém os dados do endereço cadastrado
                        var cep = $('#cep').val();
                        var cidade = $('#cidade').val();
                        var estado = $('#estado').val();
                        var rua = $('#rua').val();
                        var bairro = $('#bairro').val();
                        var numero = $('#numero').val();
                        // Exibe a mensagem com os dados do endereço selecionado
                        var enderecoSelecionado = "Endereço selecionado: " + rua + ", " + numero + " - " + bairro + " - " + cidade + "/" + estado + " - CEP: " + cep;
                        $('#mensagem').text(enderecoSelecionado);
                        // Atualiza a lista de endereços
                        atualizarListaEnderecos();
                        // Limpa os campos do formulário após cadastrar o endereço
                        $('#cep, #cidade, #estado, #rua, #bairro, #numero').val('');
                    },
                    error: function() {
                        $('#mensagem').text('Erro ao salvar endereço');
                    }
                });
            });
            // Ao digitar o CEP
            $('#cep').keyup(function() {
                var cep = $(this).val().replace(/\D/g, '');
                if (cep.length === 8) {
                    $('#mensagem').html(""); // Limpa a mensagem de erro
                    $('#cidade, #estado, #rua, #bairro').val(""); // Limpa os campos do endereço
                    $('#numero').val(""); // Limpa o campo do número
                    // Desabilita o botão de "Salvar Endereço"
                    $('.button').prop('disabled', true);
                    // Consulta o CEP na API viacep
                    $.ajax({
                        url: 'https://viacep.com.br/ws/' + cep + '/json/',
                        dataType: 'json',
                        success: function(data) {
                            if (!data.erro) {
                                $('#cidade').val(data.localidade);
                                $('#estado').val(data.uf);
                                $('#rua').val(data.logradouro);
                                $('#bairro').val(data.bairro);
                                $('#numero') // Coloca o foco no campo do número
                            }
                        },
                        complete: function() {
                            // Habilita o botão de "Salvar Endereço" após a requisição do CEP ser concluída
                            $('.button').prop('disabled', false);
                        }
                    });
                }
            });
        });
    </script>
</body>

</html>