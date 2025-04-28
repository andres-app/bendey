<?php
ob_start();
session_start();
if (!isset($_SESSION['nombre'])) {
    header("location: login");
} else {
    require "header.php";
    require "sidebar.php";

    if ($_SESSION['ventas'] == 1) {
?>
<div class="main-content">
    <section class="section">
        <div class="section-body">
            <div class="row g-3">
                <!-- Categorías -->
                <div class="col-lg-9">
                    <div class="row g-3 mb-4" id="categorias">
                        <!-- Categorías dinámicas -->
                    </div>
                    <div class="border-top my-3"></div>
                    <!-- Productos -->
                    <div class="row g-3" id="productos">
                        <!-- Productos dinámicos -->
                    </div>
                </div>

                <!-- Pedido actual -->
                <div class="col-lg-3">
                    <div class="card shadow rounded-3">
                        <div class="card-header bg-primary text-white text-center fw-bold">
                            Pedido actual
                        </div>
                        <div class="card-body" id="pedido_actual">
                            <ul class="list-group mb-3" id="lista_pedido">
                                <!-- Productos agregados -->
                            </ul>
                            <div class="d-flex justify-content-between fw-bold mb-2">
                                <span>Total:</span>
                                <span id="total_pedido">S/ 0.00</span>
                            </div>
                        </div>
                        <div class="card-footer">
                            <button class="btn btn-success w-100 py-2 fw-bold" id="btn_cobrar">Cobrar</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<script>
const categorias = [
    { id: 1, nombre: "Almuerzos", color: "#F28B82" },
    { id: 2, nombre: "Frutas", color: "#A7FFEB" },
    { id: 3, nombre: "Helados", color: "#D7AEFB" },
    { id: 4, nombre: "Desayunos", color: "#81D4FA" }
];

const productos = [
    { id: 1, nombre: "Brownie con crema", precio: 15.90, categoria: 3 },
    { id: 2, nombre: "Fresas rojas grandes", precio: 5.00, categoria: 2 },
    { id: 3, nombre: "Shawarma Mixto", precio: 17.00, categoria: 1 },
    { id: 4, nombre: "Almuerzo Menú diario #1", precio: 28.00, categoria: 1 }
];

const contenedorCategorias = document.getElementById('categorias');
const contenedorProductos = document.getElementById('productos');
const listaPedido = document.getElementById('lista_pedido');
const totalPedido = document.getElementById('total_pedido');
let pedido = [];

function renderCategorias() {
    categorias.forEach(cat => {
        const div = document.createElement('div');
        div.className = 'col-6 col-md-3';
        div.innerHTML = `
            <div class="card text-white rounded-3 shadow" style="background-color: ${cat.color}; cursor:pointer; min-height:100px;" onclick="filtrarProductos(${cat.id})">
                <div class="card-body d-flex align-items-center justify-content-center text-center">
                    <h5 class="card-title mb-0 fw-bold">${cat.nombre}</h5>
                </div>
            </div>
        `;
        contenedorCategorias.appendChild(div);
    });
}

function renderProductos(filtro = 0) {
    contenedorProductos.innerHTML = '';
    productos.filter(p => filtro === 0 || p.categoria === filtro)
        .forEach(prod => {
            const div = document.createElement('div');
            div.className = 'col-6 col-md-4 col-lg-3';
            div.innerHTML = `
                <div class="card h-100 shadow-sm rounded-3" style="cursor:pointer;" onclick="agregarProducto(${prod.id})">
                    <div class="card-body d-flex flex-column justify-content-center text-center">
                        <h6 class="card-title fw-bold mb-2">${prod.nombre}</h6>
                        <p class="card-text fw-semibold">S/ ${prod.precio.toFixed(2)}</p>
                    </div>
                </div>
            `;
            contenedorProductos.appendChild(div);
        });
}

function filtrarProductos(categoriaId) {
    renderProductos(categoriaId);
}

function agregarProducto(id) {
    const producto = productos.find(p => p.id === id);
    pedido.push(producto);
    renderPedido();
}

function renderPedido() {
    listaPedido.innerHTML = '';
    let total = 0;
    pedido.forEach((item, index) => {
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center';
        li.innerHTML = `
            ${item.nombre}
            <span>
                S/ ${item.precio.toFixed(2)}
                <button class="btn btn-danger btn-sm ms-2" onclick="eliminarProducto(${index})">&times;</button>
            </span>
        `;
        listaPedido.appendChild(li);
        total += item.precio;
    });
    totalPedido.textContent = `S/ ${total.toFixed(2)}`;
}

function eliminarProducto(index) {
    pedido.splice(index, 1);
    renderPedido();
}

document.getElementById('btn_cobrar').addEventListener('click', function() {
    if (pedido.length > 0) {
        alert('Venta realizada. Total: ' + totalPedido.textContent);
        pedido = [];
        renderPedido();
    } else {
        alert('Agrega productos antes de cobrar.');
    }
});

renderCategorias();
renderProductos();
</script>

<?php
    } else {
        require "access.php";
    }
    require "footer.php";
}
ob_end_flush();
?>