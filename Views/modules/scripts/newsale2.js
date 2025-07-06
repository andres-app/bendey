let categorias = [];
let productos = [];
let pedido = [];

// Obtenemos las categorías y productos reales al cargar
$(document).ready(function() {
    $.getJSON("Controllers/Category.php?op=listar_json", function(cats) {
        console.log("Respuesta de categorias:", cats);
        categorias = cats;
        renderCategorias();
    });

    $.getJSON("Controllers/Product.php?op=listar_json", function(prods) {
        productos = prods;
        renderProductos();
    });

    // Click cobrar
    $('#btn_cobrar').on('click', function() {
        if (pedido.length > 0) {
            alert('Venta realizada. Total: ' + $('#total_pedido').text());
            pedido = [];
            renderPedido();
        } else {
            alert('Agrega productos antes de cobrar.');
        }
    });
});

function renderCategorias() {
    const colores = ["#F28B82", "#A7FFEB", "#D7AEFB", "#81D4FA", "#FFD600", "#A7FFEB"];
    const contenedor = document.getElementById('categorias');
    contenedor.innerHTML = '';
    categorias.forEach((cat, idx) => {
        const div = document.createElement('div');
        div.className = 'col-6 col-md-3';
        div.innerHTML = `
            <div class="card text-white rounded-3 shadow" style="background-color: ${colores[idx % colores.length]}; cursor:pointer; min-height:100px;" onclick="filtrarProductos(${cat.idcategoria})">
                <div class="card-body d-flex align-items-center justify-content-center text-center">
                    <h5 class="card-title mb-0 fw-bold">${cat.nombre}</h5>
                </div>
            </div>
        `;
        contenedor.appendChild(div);
    });
}

function renderProductos(filtro = 0) {
    const contenedor = document.getElementById('productos');
    contenedor.innerHTML = '';
    productos.filter(p => filtro === 0 || p.idcategoria == filtro)
        .forEach(prod => {
            // Si no hay imagen, usa una imagen por defecto
            const imagen = prod.imagen && prod.imagen.trim() !== ""
                ? `Assets/img/products/${prod.imagen}`
                : 'Assets/img/products/default.png'; // <- pon aquí tu imagen default

            const div = document.createElement('div');
            div.className = 'col-6 col-md-4 col-lg-3';
            div.innerHTML = `
                <div class="card h-100 shadow-sm rounded-3" style="cursor:pointer;" onclick="agregarProducto(${prod.idarticulo})">
                    <img src="${imagen}" class="card-img-top p-3" alt="${prod.nombre}" style="height:120px;object-fit:contain;">
                    <div class="card-body d-flex flex-column justify-content-center text-center">
                        <h6 class="card-title fw-bold mb-2">${prod.nombre}</h6>
                        <p class="card-text mb-1"><b>Precio: </b>S/ ${parseFloat(prod.precio_venta).toFixed(2)}</p>
                        <p class="card-text mb-1"><b>Stock: </b>${prod.stock}</p>
                        <p class="card-text mb-1"><b>Medida: </b>${prod.medida || ""}</p>
                        <p class="card-text mb-1"><b>Almacén: </b>${prod.almacen || ""}</p>
                    </div>
                </div>
            `;
            contenedor.appendChild(div);
        });
}

// Para que el filtro funcione, cuélgalo de window (así el onclick del HTML lo encuentra)
window.filtrarProductos = function(categoriaId) {
    renderProductos(categoriaId);
};

window.agregarProducto = function(id) {
    const producto = productos.find(p => p.idarticulo == id);
    if (!producto) return;
    pedido.push(producto);
    renderPedido();
};

function renderPedido() {
    const listaPedido = document.getElementById('lista_pedido');
    let total = 0;
    listaPedido.innerHTML = '';
    pedido.forEach((item, idx) => {
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center';
        li.innerHTML = `
            ${item.nombre}
            <span>
                S/ ${parseFloat(item.precio_venta).toFixed(2)}
                <button class="btn btn-danger btn-sm ms-2" onclick="eliminarProducto(${idx})">&times;</button>
            </span>
        `;
        listaPedido.appendChild(li);
        total += parseFloat(item.precio_venta);
    });
    $('#total_pedido').text('S/ ' + total.toFixed(2));
}

window.eliminarProducto = function(index) {
    pedido.splice(index, 1);
    renderPedido();
};
